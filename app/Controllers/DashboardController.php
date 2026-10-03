<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Models\Ticket;

/**
 * One dashboard URL; sections depend on permissions:
 *   overview (managers, management) · queue (IT) · mine (anyone who raises tickets) · audit (auditors)
 * Sections are live regions: the poller refreshes them when data changes.
 */
final class DashboardController
{
    public function index(): void
    {
        $user = Auth::user();
        $uid = (int) $user['id'];
        $data = ['title' => 'Dashboard', 'sections' => []];

        if (can('dashboard.overview')) {
            $data['sections'][] = 'overview';
            $data += $this->overview();
        }

        if (can('ticket.view_all')) {
            $data['sections'][] = 'queue';
            $data['queue'] = Ticket::queueCounts($uid);
            // Most urgent work first: mine + unowned, by priority then deadline.
            $data['attention'] = DB::all(
                "SELECT t.id, t.ref, t.title, t.status, t.priority_id, t.created_at, t.resolve_due_at, t.resolved_at, t.closed_at,
                        t.assignee_id, p.name AS priority_name, p.tone, p.icon AS priority_icon, s.warn_percent, d.name AS department_name
                 FROM tickets t
                 JOIN priorities p ON p.id = t.priority_id
                 LEFT JOIN sla_policies s ON s.priority_id = t.priority_id
                 JOIN departments d ON d.id = t.department_id
                 WHERE t.status IN ('open','assigned','in_progress','reopened')
                   AND (t.assignee_id = ? OR t.assignee_id IS NULL)
                 ORDER BY p.sort_order, t.resolve_due_at
                 LIMIT 6",
                [$uid]
            );
        }

        if (can('ticket.view_own')) {
            $data['sections'][] = 'mine';
            $data['awaiting'] = DB::all(
                "SELECT id, ref, title FROM tickets WHERE requester_id = ? AND status = 'resolved' ORDER BY resolved_at DESC",
                [$uid]
            );
            $data['myTickets'] = DB::all(
                'SELECT t.id, t.ref, t.title, t.status, t.updated_at, a.full_name AS assignee_name
                 FROM tickets t LEFT JOIN users a ON a.id = t.assignee_id
                 WHERE t.requester_id = ? AND t.status <> \'resolved\' ORDER BY (t.status = \'closed\'), t.updated_at DESC LIMIT 5',
                [$uid]
            );
        }

        if (can('audit.view')) {
            $data['sections'][] = 'audit';
            $data['auditToday'] = (int) DB::value('SELECT COUNT(*) FROM audit_logs WHERE created_at >= CURDATE()');
            $data['failedLoginsToday'] = (int) DB::value(
                "SELECT COUNT(*) FROM audit_logs WHERE action IN ('auth.login_failed','auth.login_locked') AND created_at >= CURDATE()"
            );
            $data['deniedToday'] = (int) DB::value("SELECT COUNT(*) FROM audit_logs WHERE action = 'access.denied' AND created_at >= CURDATE()");
            $data['latestAudit'] = DB::all('SELECT action, user_email, description, created_at FROM audit_logs ORDER BY id DESC LIMIT 5');
        }

        View::show('dashboard/index', $data);
    }

    private function overview(): array
    {
        $kpi = DB::one(
            "SELECT
                SUM(status IN " . Ticket::ACTIVE . ") AS open_now,
                SUM(status IN ('open','reopened') AND assignee_id IS NULL) AS unassigned,
                SUM(status IN ('open','assigned','in_progress','reopened') AND resolve_due_at < NOW()) AS overdue,
                SUM(resolved_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS resolved_month,
                SUM(resolved_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND resolved_at <= resolve_due_at) AS on_time_month
             FROM tickets"
        );
        $kpi = array_map('intval', $kpi ?? []);
        $kpi['sla_month'] = $kpi['resolved_month'] > 0 ? (int) round($kpi['on_time_month'] / $kpi['resolved_month'] * 100) : null;

        $byPriority = DB::all(
            'SELECT p.name, p.tone, p.icon, COUNT(t.id) AS total
             FROM priorities p LEFT JOIN tickets t ON t.priority_id = p.id AND t.status IN ' . Ticket::ACTIVE . '
             GROUP BY p.id ORDER BY p.sort_order'
        );

        // 14-day trend: tickets opened vs. resolutions recorded per day.
        $days = [];
        for ($i = 13; $i >= 0; $i--) {
            $days[date('Y-m-d', strtotime("-{$i} days"))] = ['opened' => 0, 'resolved' => 0];
        }
        foreach (DB::all('SELECT DATE(created_at) d, COUNT(*) n FROM tickets WHERE created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY d') as $r) {
            $days[$r['d']]['opened'] = (int) $r['n'];
        }
        foreach (DB::all("SELECT DATE(created_at) d, COUNT(*) n FROM ticket_status_history
                          WHERE to_status = 'resolved' AND created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY d") as $r) {
            $days[$r['d']]['resolved'] = (int) $r['n'];
        }

        $workload = DB::all(
            "SELECT u.id, u.full_name,
                    SUM(t.status IN ('assigned','reopened')) AS waiting,
                    SUM(t.status = 'in_progress') AS in_progress,
                    SUM(t.status = 'on_hold') AS on_hold,
                    SUM(t.status IN ('assigned','in_progress','reopened') AND t.resolve_due_at < NOW()) AS overdue,
                    (SELECT COUNT(*) FROM tickets r WHERE r.assignee_id = u.id
                       AND r.resolved_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS resolved_month
             FROM users u
             LEFT JOIN tickets t ON t.assignee_id = u.id
             WHERE u.is_active = 1 AND u.role_id IN (
                 SELECT rp.role_id FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE p.slug = 'ticket.work')
             GROUP BY u.id ORDER BY u.full_name"
        );

        return ['kpi' => $kpi, 'byPriority' => $byPriority, 'trend' => $days, 'workload' => $workload];
    }
}
