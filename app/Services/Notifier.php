<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * Creates in-app notifications (always) and queues emails (per-user choice).
 * The acting user is never notified about their own action. Emails are sent
 * by tools/cron.php so a slow SMTP server never delays a web request.
 */
final class Notifier
{
    /** event => [label shown in preferences, email on by default, only for IT staff] */
    public const EVENTS = [
        'ticket.assigned' => ['A ticket is assigned to me, or someone picks up my ticket', true,  false],
        'ticket.comment'  => ['New replies on my tickets',                             true,  false],
        'ticket.status'   => ['Status changes on my tickets',                          false, false],
        'ticket.resolved' => ['My ticket is resolved and needs my confirmation',       true,  false],
        'ticket.created'  => ['A new ticket is reported',                              false, true],
        'ticket.sla_risk' => ['A ticket is close to, or past, its SLA target',          true,  true],
    ];

    /** @param int[] $userIds */
    public static function notify(array $userIds, string $event, array $ticket, string $title, string $body = ''): void
    {
        $actor = Auth::id();
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn ($id) => $id > 0 && $id !== $actor)));
        if (!$userIds) {
            return;
        }
        $title = mb_substr($title, 0, 150);
        $body = mb_substr($body, 0, 500);
        $url = '/tickets/' . $ticket['id'];

        $emailOn = setting('notifications.email', '1') === '1';
        $recipients = DB::all(
            'SELECT u.id, u.full_name, u.email, np.email AS pref_email
             FROM users u LEFT JOIN notification_preferences np ON np.user_id = u.id AND np.event = ?
             WHERE u.is_active = 1 AND u.id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')',
            [$event, ...$userIds]
        );

        foreach ($recipients as $r) {
            DB::run('INSERT INTO notifications (user_id, ticket_id, event, title, body, url) VALUES (?, ?, ?, ?, ?, ?)',
                [(int) $r['id'], (int) $ticket['id'], $event, $title, $body, $url]);

            $wantsEmail = $r['pref_email'] === null ? (self::EVENTS[$event][1] ?? false) : (int) $r['pref_email'] === 1;
            if ($emailOn && $wantsEmail) {
                [$html, $text] = Mailer::render($r['full_name'], $title, $body, $ticket, absolute_url($url));
                DB::run('INSERT INTO email_queue (to_email, to_name, subject, body_html, body_text) VALUES (?, ?, ?, ?, ?)',
                    [$r['email'], $r['full_name'], '[RUMA IT] ' . $title, $html, $text]);
            }
        }
    }

    /** Everyone who works the queue (for new and unassigned tickets). */
    public static function itStaffIds(): array
    {
        return self::idsWithPermission('ticket.work');
    }

    /** IT Managers (escalation for breached SLAs). */
    public static function managerIds(): array
    {
        return self::idsWithPermission('ticket.reassign');
    }

    private static function idsWithPermission(string $slug): array
    {
        return DB::run(
            'SELECT DISTINCT u.id FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE p.slug = ? AND u.is_active = 1',
            [$slug]
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Events this user can receive (IT-only events hidden from other staff). */
    public static function eventsFor(bool $isItStaff): array
    {
        return array_filter(self::EVENTS, static fn ($e) => $isItStaff || !$e[2]);
    }
}
