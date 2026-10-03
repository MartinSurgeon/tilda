<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * Creates in-app notifications. The acting user is never notified about their
 * own action. (Phase 3 adds per-user preferences and email delivery here.)
 */
final class Notifier
{
    public const EVENTS = [
        'ticket.created'   => 'New ticket',
        'ticket.assigned'  => 'Ticket assigned',
        'ticket.status'    => 'Status changed',
        'ticket.comment'   => 'New reply',
        'ticket.resolved'  => 'Ticket resolved',
        'ticket.sla_risk'  => 'SLA at risk',
    ];

    /** @param int[] $userIds */
    public static function notify(array $userIds, string $event, array $ticket, string $title, string $body = ''): void
    {
        $actor = Auth::id();
        $userIds = array_unique(array_filter(array_map('intval', $userIds), static fn ($id) => $id > 0 && $id !== $actor));
        foreach ($userIds as $userId) {
            DB::run(
                'INSERT INTO notifications (user_id, ticket_id, event, title, body, url) VALUES (?, ?, ?, ?, ?, ?)',
                [$userId, (int) $ticket['id'], $event, mb_substr($title, 0, 150), mb_substr($body, 0, 500), '/tickets/' . $ticket['id']]
            );
        }
    }

    /** Everyone who works the queue (for new and unassigned tickets). */
    public static function itStaffIds(): array
    {
        return DB::run(
            "SELECT DISTINCT u.id FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE p.slug = 'ticket.work' AND u.is_active = 1"
        )->fetchAll(\PDO::FETCH_COLUMN);
    }
}
