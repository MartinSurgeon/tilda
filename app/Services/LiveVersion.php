<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Gate;

/**
 * A cheap fingerprint of "everything this user can see". The page embeds it;
 * the poller compares it every few seconds and refreshes live regions only
 * when it changes. All inputs are indexed MAX/COUNT lookups.
 */
final class LiveVersion
{
    public static function for(array $user): string
    {
        $uid = (int) $user['id'];
        $parts = [];

        if (Gate::any('ticket.view_all', 'dashboard.overview')) {
            $parts[] = DB::one('SELECT MAX(updated_at) u, COUNT(*) c FROM tickets');
            $parts[] = DB::value('SELECT MAX(id) FROM ticket_status_history');
            $parts[] = DB::value('SELECT MAX(id) FROM ticket_comments');
        } elseif (Gate::allows('ticket.view_own')) {
            $parts[] = DB::one('SELECT MAX(updated_at) u, COUNT(*) c FROM tickets WHERE requester_id = ?', [$uid]);
            $parts[] = DB::value(
                'SELECT MAX(h.id) FROM ticket_status_history h JOIN tickets t ON t.id = h.ticket_id WHERE t.requester_id = ?', [$uid]);
            $parts[] = DB::value(
                'SELECT MAX(c.id) FROM ticket_comments c JOIN tickets t ON t.id = c.ticket_id WHERE t.requester_id = ? AND c.is_internal = 0', [$uid]);
        }
        if (Gate::allows('audit.view')) {
            $parts[] = DB::value('SELECT MAX(id) FROM audit_logs');
        }
        $parts[] = DB::one('SELECT MAX(id) m, COUNT(read_at) r FROM notifications WHERE user_id = ?', [$uid]);

        return substr(hash('sha256', json_encode($parts)), 0, 16);
    }
}
