<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\Ticket;

/** Background jobs, run by tools/cron.php every 5 minutes. Each returns a count for the log. */
final class Scheduler
{
    /** Warn once when a ticket passes its SLA warning point (e.g. 80% of the time used). */
    public static function slaWarnings(): int
    {
        $rows = DB::all(
            "SELECT t.id FROM tickets t JOIN sla_policies s ON s.priority_id = t.priority_id
             WHERE t.status IN ('open','assigned','in_progress','reopened')
               AND t.sla_warned_at IS NULL AND t.resolve_due_at > NOW()
               AND TIMESTAMPDIFF(SECOND, t.created_at, NOW())
                   >= TIMESTAMPDIFF(SECOND, t.created_at, t.resolve_due_at) * s.warn_percent / 100"
        );
        foreach ($rows as $row) {
            $t = Ticket::find((int) $row['id']);
            DB::run('UPDATE tickets SET sla_warned_at = NOW() WHERE id = ? AND sla_warned_at IS NULL', [$t['id']]);
            $who = $t['assignee_id'] ? [(int) $t['assignee_id']] : Notifier::itStaffIds();
            Notifier::notify($who, 'ticket.sla_risk', $t,
                "{$t['ref']} is close to its deadline",
                'Due ' . fmt_date($t['resolve_due_at']) . ' · ' . $t['priority_name'] . ' · ' . $t['title']);
            AuditLogger::log('ticket.sla_warning', 'ticket', $t['id'], "{$t['ref']} passed its SLA warning point");
        }
        return count($rows);
    }

    /** Escalate once when the resolution target is missed. On-hold tickets are paused. */
    public static function slaBreaches(): int
    {
        $rows = DB::all(
            "SELECT id FROM tickets
             WHERE status IN ('open','assigned','in_progress','reopened')
               AND sla_breach_notified_at IS NULL AND resolve_due_at < NOW()"
        );
        foreach ($rows as $row) {
            $t = Ticket::find((int) $row['id']);
            DB::run('UPDATE tickets SET sla_breach_notified_at = NOW() WHERE id = ? AND sla_breach_notified_at IS NULL', [$t['id']]);
            $who = array_merge(Notifier::managerIds(), $t['assignee_id'] ? [(int) $t['assignee_id']] : Notifier::itStaffIds());
            Notifier::notify($who, 'ticket.sla_risk', $t,
                "{$t['ref']} has missed its deadline",
                $t['priority_name'] . ' · ' . ($t['assignee_name'] ? 'Assigned to ' . $t['assignee_name'] : 'Not assigned') . ' · ' . $t['title']);
            AuditLogger::log('ticket.sla_breached', 'ticket', $t['id'], "{$t['ref']} missed its resolution target");
        }
        return count($rows);
    }

    /** Close resolved tickets the requester did not confirm within N days. */
    public static function autoClose(): int
    {
        $days = max(1, (int) setting('tickets.auto_close_days', '5'));
        $rows = DB::all(
            "SELECT id FROM tickets WHERE status = 'resolved' AND resolved_at < NOW() - INTERVAL ? DAY",
            [$days]
        );
        foreach ($rows as $row) {
            $t = Ticket::find((int) $row['id']);
            DB::transaction(static function () use ($t, $days): void {
                $n = DB::run("UPDATE tickets SET status = 'closed', closed_at = NOW() WHERE id = ? AND status = 'resolved'", [$t['id']])->rowCount();
                if ($n === 0) {
                    return;
                }
                DB::run('INSERT INTO ticket_status_history (ticket_id, from_status, to_status, changed_by, note) VALUES (?, ?, ?, NULL, ?)',
                    [$t['id'], 'resolved', 'closed', "Closed automatically: no reply within {$days} days of being resolved."]);
                AuditLogger::log('ticket.auto_closed', 'ticket', $t['id'], "{$t['ref']} closed automatically after {$days} days");
            });
            Notifier::notify([(int) $t['requester_id']], 'ticket.status', $t, "{$t['ref']} has been closed",
                "It was resolved {$days} days ago with no reply. If the problem is back, please report it again.");
        }
        return count($rows);
    }

    /** Send queued emails. Failed messages are retried up to 3 times. */
    public static function sendMail(int $batch = 50): array
    {
        $sent = 0;
        $failed = 0;
        $rows = DB::all("SELECT * FROM email_queue WHERE status = 'pending' AND attempts < 3 ORDER BY id LIMIT ?", [$batch]);
        foreach ($rows as $m) {
            try {
                Mailer::send($m['to_email'], $m['to_name'], $m['subject'], $m['body_html'], $m['body_text']);
                DB::run("UPDATE email_queue SET status = 'sent', sent_at = NOW(), attempts = attempts + 1 WHERE id = ?", [$m['id']]);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                DB::run(
                    "UPDATE email_queue SET attempts = attempts + 1, last_error = ?,
                            status = IF(attempts + 1 >= 3, 'failed', 'pending') WHERE id = ?",
                    [mb_substr($e->getMessage(), 0, 500), $m['id']]
                );
            }
        }
        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Once a day: record the current end-of-chain fingerprints and email them to
     * everyone who can verify the audit log. Those emails live outside the
     * database, so even someone able to rewrite the whole chain cannot make the
     * earlier fingerprints match again.
     */
    public static function auditAnchor(): int
    {
        if (DB::value("SELECT 1 FROM audit_logs WHERE action = 'audit.anchor' AND created_at >= CURDATE() LIMIT 1")) {
            return 0;
        }
        $heads = [];
        foreach (['audit_logs', 'db_change_logs'] as $chain) {
            $heads[$chain] = [
                'rows' => (int) DB::value("SELECT COUNT(*) FROM {$chain}"),
                'hash' => (string) DB::value('SELECT last_hash FROM audit_chain_head WHERE chain = ?', [$chain]),
            ];
        }
        AuditLogger::log('audit.anchor', 'audit', null, 'Daily audit fingerprint recorded and sent to auditors', $heads);
        file_put_contents(BASE_PATH . '/storage/logs/anchors.log', date('c') . ' ' . json_encode($heads) . "\n", FILE_APPEND | LOCK_EX);

        $recipients = DB::all(
            "SELECT DISTINCT u.full_name, u.email FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id JOIN permissions p ON p.id = rp.permission_id
             WHERE p.slug = 'audit.verify' AND u.is_active = 1"
        );
        $lines = [
            'Keep this email. It lets you prove later that the activity log was not rewritten.',
            "Activity log: {$heads['audit_logs']['rows']} entries\n{$heads['audit_logs']['hash']}",
            "Data change log: {$heads['db_change_logs']['rows']} entries\n{$heads['db_change_logs']['hash']}",
        ];
        foreach ($recipients as $r) {
            [$html, $text] = Mailer::renderMessage($r['full_name'], 'Daily audit fingerprint ' . date('j M Y'), $lines,
                absolute_url('/audit/integrity'), 'Open safety check');
            DB::run('INSERT INTO email_queue (to_email, to_name, subject, body_html, body_text) VALUES (?, ?, ?, ?, ?)',
                [$r['email'], $r['full_name'], '[RUMA IT] Daily audit fingerprint ' . date('Y-m-d'), $html, $text]);
        }
        return count($recipients);
    }

    /** Keep operational tables small. (Audit tables are never pruned.) */
    public static function housekeeping(): int
    {
        $n = DB::run('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 30 DAY')->rowCount();
        $n += DB::run("DELETE FROM email_queue WHERE status = 'sent' AND sent_at < NOW() - INTERVAL 30 DAY")->rowCount();
        $n += DB::run('DELETE FROM notifications WHERE read_at IS NOT NULL AND read_at < NOW() - INTERVAL 180 DAY')->rowCount();
        return $n;
    }
}
