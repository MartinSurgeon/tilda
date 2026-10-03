<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Gate;
use App\Core\HttpException;
use App\Models\Ticket;

/**
 * Every ticket state change goes through here: one transaction per action,
 * with status history, audit entry and notifications written together.
 * Callers have already passed TicketPolicy checks; the guards below re-check
 * the invariants that must never break.
 */
final class TicketService
{
    /**
     * @param array{title: string, description: string, category_id: int, subcategory_id: ?int,
     *              department_id: int, location: string, priority_id: int} $data
     * @param array $files validated uploads (Uploads::fromRequest)
     */
    public static function create(array $data, array $user, array $files = []): array
    {
        $id = DB::transaction(static function () use ($data, $user, $files): int {
            $year = (int) date('Y');
            // Atomic per-year counter; the row lock lasts until commit, so refs are gap-free.
            DB::run(
                'INSERT INTO ticket_sequences (seq_year, last_value) VALUES (?, LAST_INSERT_ID(1))
                 ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(last_value + 1)',
                [$year]
            );
            $seq = (int) DB::value('SELECT LAST_INSERT_ID()');
            $ref = sprintf('%s-%d-%06d', setting('tickets.ref_prefix', 'RUMA'), $year, $seq);

            $now = date('Y-m-d H:i:s');
            $due = Sla::dueDates($data['priority_id'], $now);

            $id = DB::insert(
                'INSERT INTO tickets (ref, title, description, category_id, subcategory_id, department_id, location,
                                      priority_id, status, requester_id, response_due_at, resolve_due_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'open\', ?, ?, ?, ?)',
                [
                    $ref, $data['title'], $data['description'], $data['category_id'], $data['subcategory_id'],
                    $data['department_id'], $data['location'], $data['priority_id'], (int) $user['id'],
                    $due['response_due_at'], $due['resolve_due_at'], $now,
                ]
            );
            self::history($id, null, 'open', (int) $user['id'], '');
            foreach ($files as $file) {
                self::attach($id, null, $file, (int) $user['id'], false);
            }
            AuditLogger::log('ticket.created', 'ticket', $id, "Created {$ref}", [
                'priority_id' => $data['priority_id'], 'category_id' => $data['category_id'], 'attachments' => count($files),
            ]);
            return $id;
        });

        $ticket = Ticket::find($id);
        Notifier::notify(Notifier::itStaffIds(), 'ticket.created', $ticket,
            "New {$ticket['priority_name']} ticket {$ticket['ref']}", $ticket['title']);
        return $ticket;
    }

    /** Assign (or reassign) to a technician. $assigneeId === actor means "Accept". */
    public static function assign(array $t, int $assigneeId, array $actor): void
    {
        $tech = DB::one(
            "SELECT u.id, u.full_name FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id JOIN permissions p ON p.id = rp.permission_id
             WHERE u.id = ? AND u.is_active = 1 AND p.slug = 'ticket.work'",
            [$assigneeId]
        );
        if (!$tech) {
            throw new HttpException(422, 'That person cannot be assigned tickets.');
        }
        if ((int) ($t['assignee_id'] ?? 0) === $assigneeId) {
            return;
        }

        DB::transaction(static function () use ($t, $tech, $actor): void {
            // Optimistic lock: refuse if someone else changed the owner meanwhile.
            $sql = 'UPDATE tickets SET assignee_id = ?, status = IF(status IN (\'open\',\'reopened\'), \'assigned\', status)
                    WHERE id = ? AND ' . ($t['assignee_id'] === null ? 'assignee_id IS NULL' : 'assignee_id = ?');
            $params = [(int) $tech['id'], (int) $t['id']];
            if ($t['assignee_id'] !== null) {
                $params[] = (int) $t['assignee_id'];
            }
            if (DB::run($sql, $params)->rowCount() === 0) {
                throw new HttpException(409, 'Someone else changed this ticket a moment ago. Please check it again.');
            }
            self::markFirstResponse($t, $actor);
            if (in_array($t['status'], ['open', 'reopened'], true)) {
                self::history((int) $t['id'], $t['status'], 'assigned', (int) $actor['id'], 'Assigned to ' . $tech['full_name']);
            }
            $self = (int) $tech['id'] === (int) $actor['id'];
            AuditLogger::log($t['assignee_id'] === null ? 'ticket.assigned' : 'ticket.reassigned', 'ticket', $t['id'],
                ($self ? 'Accepted ' : 'Assigned ') . $t['ref'] . ($self ? '' : " to {$tech['full_name']}"),
                ['from' => $t['assignee_id'], 'to' => (int) $tech['id']]);
        });

        Notifier::notify([(int) $tech['id']], 'ticket.assigned', $t, "{$t['ref']} is assigned to you", $t['title']);
        Notifier::notify([(int) $t['requester_id']], 'ticket.assigned', $t,
            "{$tech['full_name']} is looking at {$t['ref']}", $t['title']);
    }

    /** The assignee hands the ticket back to the queue. */
    public static function release(array $t, array $actor): void
    {
        DB::transaction(static function () use ($t, $actor): void {
            $closeHold = $t['status'] === 'on_hold' ? self::holdEndSql() : '';
            DB::run("UPDATE tickets SET assignee_id = NULL, status = 'open'{$closeHold} WHERE id = ?", [(int) $t['id']]);
            self::history((int) $t['id'], $t['status'], 'open', (int) $actor['id'], 'Returned to the queue');
            AuditLogger::log('ticket.released', 'ticket', $t['id'], "Returned {$t['ref']} to the queue");
        });
        Notifier::notify(Notifier::itStaffIds(), 'ticket.status', $t, "{$t['ref']} is back in the queue", $t['title']);
    }

    public static function transition(array $t, string $to, string $note, array $actor): void
    {
        $allowed = TicketPolicy::transitions($t, $actor);
        if (!isset($allowed[$to])) {
            throw new HttpException(403);
        }
        if ($allowed[$to]['note'] && trim($note) === '') {
            throw new HttpException(422, 'Please add a short explanation.');
        }
        $from = $t['status'];

        DB::transaction(static function () use ($t, $from, $to, $note, $actor, $allowed): void {
            $set = ['status = ?'];
            $params = [$to];

            if ($from === 'on_hold') {
                $set[] = ltrim(self::holdEndSql(), ', ');
            }
            if ($to === 'on_hold') {
                $set[] = 'on_hold_since = NOW()';
            }
            if ($to === 'resolved') {
                $set[] = 'resolved_at = NOW()';
            }
            if ($to === 'closed') {
                $set[] = 'closed_at = NOW()';
            }
            if ($to === 'reopened') {
                $set[] = 'resolved_at = NULL';
                $set[] = 'closed_at = NULL';
            }

            $params[] = (int) $t['id'];
            $params[] = $from;
            $n = DB::run('UPDATE tickets SET ' . implode(', ', $set) . ' WHERE id = ? AND status = ?', $params)->rowCount();
            if ($n === 0) {
                throw new HttpException(409, 'Someone else changed this ticket a moment ago. Please check it again.');
            }
            if ($allowed[$to]['as'] === 'it') {
                self::markFirstResponse($t, $actor);
            }
            self::history((int) $t['id'], $from, $to, (int) $actor['id'], $note);
            AuditLogger::log('ticket.status_changed', 'ticket', $t['id'],
                "{$t['ref']}: " . TicketMeta::STATUSES[$from]['label'] . ' → ' . TicketMeta::STATUSES[$to]['label'],
                ['from' => $from, 'to' => $to, 'as' => $allowed[$to]['as']]);
        });

        $label = TicketMeta::STATUSES[$to]['label'];
        if ($to === 'resolved') {
            Notifier::notify([(int) $t['requester_id']], 'ticket.resolved', $t,
                "{$t['ref']} is resolved. Please confirm the fix", $note);
        } else {
            Notifier::notify([(int) $t['requester_id'], (int) $t['assignee_id']], 'ticket.status', $t,
                "{$t['ref']} is now {$label}", $note ?: $t['title']);
        }
        if ($to === 'reopened' && $t['assignee_id'] === null) {
            Notifier::notify(Notifier::itStaffIds(), 'ticket.status', $t, "{$t['ref']} was reopened", $note);
        }
    }

    public static function changePriority(array $t, int $priorityId, string $reason, array $actor): void
    {
        if ($priorityId === (int) $t['priority_id']) {
            return;
        }
        $due = Sla::dueDates($priorityId, $t['created_at'], (int) $t['hold_minutes']);
        DB::transaction(static function () use ($t, $priorityId, $reason, $actor, $due): void {
            DB::run('UPDATE tickets SET priority_id = ?, response_due_at = ?, resolve_due_at = ?, sla_warned_at = NULL WHERE id = ?',
                [$priorityId, $due['response_due_at'], $due['resolve_due_at'], (int) $t['id']]);
            $name = (string) DB::value('SELECT name FROM priorities WHERE id = ?', [$priorityId]);
            DB::run('INSERT INTO ticket_comments (ticket_id, user_id, body, is_internal) VALUES (?, ?, ?, 1)',
                [(int) $t['id'], (int) $actor['id'], "Priority changed from {$t['priority_name']} to {$name}. Reason: {$reason}"]);
            AuditLogger::log('ticket.priority_changed', 'ticket', $t['id'], "{$t['ref']}: priority {$t['priority_name']} → {$name}",
                ['from' => (int) $t['priority_id'], 'to' => $priorityId, 'reason' => $reason]);
        });
    }

    public static function comment(array $t, string $body, bool $internal, array $actor, array $files = []): void
    {
        DB::transaction(static function () use ($t, $body, $internal, $actor, $files): void {
            $commentId = DB::insert('INSERT INTO ticket_comments (ticket_id, user_id, body, is_internal) VALUES (?, ?, ?, ?)',
                [(int) $t['id'], (int) $actor['id'], $body, $internal ? 1 : 0]);
            foreach ($files as $file) {
                self::attach((int) $t['id'], $commentId, $file, (int) $actor['id'], $internal);
            }
            if (!$internal) {
                self::markFirstResponse($t, $actor);
            }
            // Touch so lists sorted by "updated" and live dashboards notice the reply.
            DB::run('UPDATE tickets SET updated_at = NOW() WHERE id = ?', [(int) $t['id']]);
            AuditLogger::log($internal ? 'ticket.internal_note' : 'ticket.comment', 'ticket', $t['id'],
                ($internal ? 'Internal note on ' : 'Reply on ') . $t['ref'], ['comment_id' => $commentId, 'attachments' => count($files)]);
        });

        $excerpt = mb_strimwidth($body, 0, 140, '…');
        $name = $actor['full_name'];
        if ($internal) {
            Notifier::notify([(int) $t['assignee_id']], 'ticket.comment', $t, "{$name} added a note to {$t['ref']}", $excerpt);
        } else {
            Notifier::notify([(int) $t['requester_id'], (int) $t['assignee_id']], 'ticket.comment', $t,
                "{$name} replied on {$t['ref']}", $excerpt);
        }
    }

    // ------------------------------------------------------------------

    private static function attach(int $ticketId, ?int $commentId, array $file, int $userId, bool $internal): void
    {
        $meta = Uploads::store($file);
        $id = DB::insert(
            'INSERT INTO ticket_attachments (ticket_id, comment_id, uploaded_by, original_name, stored_name, mime_type, size_bytes, sha256, is_internal)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$ticketId, $commentId, $userId, $meta['original_name'], $meta['stored_name'], $meta['mime_type'],
             $meta['size_bytes'], $meta['sha256'], $internal ? 1 : 0]
        );
        AuditLogger::log('ticket.attachment_uploaded', 'ticket', $ticketId, "Attached {$meta['original_name']}", [
            'attachment_id' => $id, 'mime' => $meta['mime_type'], 'size' => $meta['size_bytes'], 'sha256' => $meta['sha256'],
        ]);
    }

    private static function history(int $ticketId, ?string $from, string $to, int $userId, string $note): void
    {
        DB::run('INSERT INTO ticket_status_history (ticket_id, from_status, to_status, changed_by, note) VALUES (?, ?, ?, ?, ?)',
            [$ticketId, $from, $to, $userId, mb_substr($note, 0, 500)]);
    }

    /** The first IT action (not the requester's own) stops the response-time clock. */
    private static function markFirstResponse(array $t, array $actor): void
    {
        if ($t['first_response_at'] === null && (int) $actor['id'] !== (int) $t['requester_id'] && Gate::allows('ticket.work')) {
            DB::run('UPDATE tickets SET first_response_at = NOW() WHERE id = ? AND first_response_at IS NULL', [(int) $t['id']]);
        }
    }

    /** SQL fragment that ends a hold: adds the paused time and pushes the deadline back. */
    private static function holdEndSql(): string
    {
        return ', hold_minutes = hold_minutes + TIMESTAMPDIFF(MINUTE, on_hold_since, NOW()),
                  resolve_due_at = resolve_due_at + INTERVAL TIMESTAMPDIFF(MINUTE, on_hold_since, NOW()) MINUTE,
                  on_hold_since = NULL';
    }
}
