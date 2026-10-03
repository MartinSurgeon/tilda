<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** Reference data used by forms and filters. */
final class Lookup
{
    public static function categories(bool $activeOnly = true): array
    {
        return DB::all('SELECT * FROM categories WHERE parent_id IS NULL'
            . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, name');
    }

    /** Sub-categories grouped by parent id. */
    public static function subcategories(bool $activeOnly = true): array
    {
        $out = [];
        foreach (DB::all('SELECT * FROM categories WHERE parent_id IS NOT NULL'
            . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, name') as $row) {
            $out[(int) $row['parent_id']][] = $row;
        }
        return $out;
    }

    public static function departments(bool $activeOnly = true): array
    {
        return DB::all('SELECT * FROM departments' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY name');
    }

    public static function priorities(): array
    {
        return DB::all(
            'SELECT p.*, s.response_minutes, s.resolution_minutes, s.warn_percent
             FROM priorities p LEFT JOIN sla_policies s ON s.priority_id = p.id ORDER BY p.sort_order'
        );
    }

    /** Active users who can work tickets (assignment targets). */
    public static function technicians(): array
    {
        return DB::all(
            "SELECT u.id, u.full_name,
                    (SELECT COUNT(*) FROM tickets t WHERE t.assignee_id = u.id
                       AND t.status IN ('assigned','in_progress','on_hold','reopened')) AS open_count
             FROM users u
             WHERE u.is_active = 1 AND u.role_id IN (
                 SELECT rp.role_id FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
                 WHERE p.slug = 'ticket.work')
             ORDER BY u.full_name"
        );
    }
}
