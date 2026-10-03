<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Ticket
{
    private const SELECT = "
        SELECT t.*,
               p.name AS priority_name, p.slug AS priority_slug, p.tone, p.icon AS priority_icon, p.sort_order AS priority_order,
               s.warn_percent,
               c.name AS category_name, c.icon AS category_icon, sc.name AS subcategory_name,
               d.name AS department_name,
               r.full_name AS requester_name, r.email AS requester_email, r.phone AS requester_phone,
               a.full_name AS assignee_name
        FROM tickets t
        JOIN priorities p   ON p.id = t.priority_id
        LEFT JOIN sla_policies s ON s.priority_id = t.priority_id
        JOIN categories c   ON c.id = t.category_id
        LEFT JOIN categories sc ON sc.id = t.subcategory_id
        JOIN departments d  ON d.id = t.department_id
        JOIN users r        ON r.id = t.requester_id
        LEFT JOIN users a   ON a.id = t.assignee_id";

    public const ACTIVE = "('open','assigned','in_progress','on_hold','reopened')";

    public static function find(int $id): ?array
    {
        return DB::one(self::SELECT . ' WHERE t.id = ?', [$id]);
    }

    /**
     * Filtered, paginated list.
     * @param array{view?: string, q?: string, status?: string, priority?: int, category?: int,
     *              department?: int, assignee?: int|string, requester?: int} $f
     * @return array{rows: array, total: int}
     */
    public static function search(array $f, int $userId, int $page, int $perPage): array
    {
        $where = [];
        $params = [];

        switch ($f['view'] ?? 'all') {
            case 'mine':       $where[] = 't.assignee_id = ? AND t.status IN ' . self::ACTIVE; $params[] = $userId; break;
            case 'unassigned': $where[] = "t.assignee_id IS NULL AND t.status IN ('open','reopened')"; break;
            case 'active':     $where[] = 't.status IN ' . self::ACTIVE; break;
            case 'overdue':    $where[] = 't.status IN ' . self::ACTIVE . ' AND t.resolve_due_at < NOW()'; break;
            case 'done':       $where[] = "t.status IN ('resolved','closed')"; break;
        }
        if (!empty($f['requester'])) {
            $where[] = 't.requester_id = ?';
            $params[] = (int) $f['requester'];
        }
        if (($f['q'] ?? '') !== '') {
            $where[] = '(t.ref LIKE ? OR t.title LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($params, $like, $like);
        }
        if (!empty($f['status'])) {
            $where[] = 't.status = ?';
            $params[] = $f['status'];
        }
        foreach (['priority' => 't.priority_id', 'category' => 't.category_id', 'department' => 't.department_id'] as $key => $col) {
            if (!empty($f[$key])) {
                $where[] = "{$col} = ?";
                $params[] = (int) $f[$key];
            }
        }
        if (($f['assignee'] ?? '') === 'none') {
            $where[] = 't.assignee_id IS NULL';
        } elseif (!empty($f['assignee'])) {
            $where[] = 't.assignee_id = ?';
            $params[] = (int) $f['assignee'];
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) DB::value('SELECT COUNT(*) FROM tickets t' . $whereSql, $params);

        // Active work: most urgent first, then the deadline. History: newest first.
        $order = in_array($f['view'] ?? '', ['done'], true) || ($f['sort'] ?? '') === 'updated'
            ? 't.updated_at DESC'
            : "(t.status IN ('resolved','closed')), p.sort_order, t.resolve_due_at, t.id";

        $rows = DB::all(
            self::SELECT . $whereSql . " ORDER BY {$order} LIMIT ? OFFSET ?",
            [...$params, $perPage, ($page - 1) * $perPage]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** Counts for the queue tabs. */
    public static function queueCounts(int $userId): array
    {
        return DB::one(
            'SELECT
                SUM(assignee_id = ? AND status IN ' . self::ACTIVE . ") AS mine,
                SUM(assignee_id IS NULL AND status IN ('open','reopened')) AS unassigned,
                SUM(status IN " . self::ACTIVE . ') AS active,
                SUM(status IN ' . self::ACTIVE . " AND resolve_due_at < NOW()) AS overdue
             FROM tickets",
            [$userId]
        ) ?? [];
    }

    public static function comments(int $ticketId, bool $includeInternal): array
    {
        return DB::all(
            'SELECT c.*, u.full_name, r.slug AS role
             FROM ticket_comments c JOIN users u ON u.id = c.user_id JOIN roles r ON r.id = u.role_id
             WHERE c.ticket_id = ?' . ($includeInternal ? '' : ' AND c.is_internal = 0') . '
             ORDER BY c.created_at, c.id',
            [$ticketId]
        );
    }

    public static function history(int $ticketId): array
    {
        return DB::all(
            "SELECT h.*, COALESCE(u.full_name, 'System') AS full_name FROM ticket_status_history h LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.ticket_id = ? ORDER BY h.created_at, h.id",
            [$ticketId]
        );
    }

    public static function attachments(int $ticketId, bool $includeInternal): array
    {
        return DB::all(
            'SELECT a.*, u.full_name FROM ticket_attachments a JOIN users u ON u.id = a.uploaded_by
             WHERE a.ticket_id = ?' . ($includeInternal ? '' : ' AND a.is_internal = 0') . '
             ORDER BY a.created_at, a.id',
            [$ticketId]
        );
    }
}
