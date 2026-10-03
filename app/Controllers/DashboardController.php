<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;

/**
 * One dashboard URL; the sections shown depend on the user's permissions.
 * Live counts and trends are expanded in Phase 3.
 */
final class DashboardController
{
    public function index(): void
    {
        $user = Auth::user();
        $data = ['title' => 'Dashboard', 'sections' => []];

        if (can('ticket.view_all')) {
            $data['sections'][] = 'queue';
            $data['queue'] = DB::one(
                "SELECT
                    SUM(assignee_id IS NULL AND status IN ('open','reopened'))                    AS unassigned,
                    SUM(assignee_id = :uid AND status IN ('assigned','in_progress','reopened'))   AS mine_active,
                    SUM(assignee_id = :uid2 AND status = 'on_hold')                               AS mine_on_hold,
                    SUM(status NOT IN ('resolved','closed') AND resolve_due_at < NOW())           AS overdue
                 FROM tickets",
                ['uid' => $user['id'], 'uid2' => $user['id']]
            );
        }

        if (can('dashboard.overview')) {
            $data['sections'][] = 'overview';
            $data['byStatus'] = DB::all(
                "SELECT status, COUNT(*) AS total FROM tickets GROUP BY status"
            );
        }

        if (can('ticket.view_own')) {
            $data['sections'][] = 'mine';
            $data['myTickets'] = DB::all(
                'SELECT t.id, t.ref, t.title, t.status, t.updated_at, p.name AS priority, p.tone, p.icon
                 FROM tickets t JOIN priorities p ON p.id = t.priority_id
                 WHERE t.requester_id = ? ORDER BY t.updated_at DESC LIMIT 5',
                [$user['id']]
            );
        }

        if (can('audit.view')) {
            $data['sections'][] = 'audit';
            $data['auditToday'] = (int) DB::value('SELECT COUNT(*) FROM audit_logs WHERE created_at >= CURDATE()');
            $data['failedLoginsToday'] = (int) DB::value(
                "SELECT COUNT(*) FROM audit_logs WHERE action IN ('auth.login_failed','auth.login_locked') AND created_at >= CURDATE()"
            );
        }

        View::show('dashboard/index', $data);
    }
}
