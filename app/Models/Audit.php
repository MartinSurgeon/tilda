<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Request;

/** Read-only queries over the two audit tables. The app never updates or deletes them. */
final class Audit
{
    public const OPERATIONS = ['INSERT' => 'Created', 'UPDATE' => 'Changed', 'DELETE' => 'Deleted'];

    /** Filters for the activity log, read from the query string. */
    public static function activityFilters(): array
    {
        return [
            'from'   => self::date((string) Request::query('from', '')),
            'to'     => self::date((string) Request::query('to', '')),
            'user'   => (string) Request::query('user', ''),          // user id, or "system"
            'action' => mb_substr((string) Request::query('action', ''), 0, 60),
            'entity' => mb_substr((string) Request::query('entity', ''), 0, 40),
            'id'     => mb_substr((string) Request::query('id', ''), 0, 40),
            'ip'     => mb_substr((string) Request::query('ip', ''), 0, 45),
            'q'      => mb_substr((string) Request::query('q', ''), 0, 100),
        ];
    }

    public static function changeFilters(): array
    {
        $op = strtoupper((string) Request::query('op', ''));
        return [
            'from'  => self::date((string) Request::query('from', '')),
            'to'    => self::date((string) Request::query('to', '')),
            'user'  => (string) Request::query('user', ''),
            'table' => preg_replace('/[^a-z_]/', '', (string) Request::query('table', '')),
            'op'    => isset(self::OPERATIONS[$op]) ? $op : '',
            'id'    => mb_substr((string) Request::query('id', ''), 0, 64),
        ];
    }

    /** @return array{0: string, 1: array} WHERE clause and params */
    public static function activityWhere(array $f): array
    {
        $w = [];
        $p = [];
        self::common($f, 'a', $w, $p);
        if ($f['action'] !== '') { $w[] = 'a.action = ?'; $p[] = $f['action']; }
        if ($f['entity'] !== '') { $w[] = 'a.entity_type = ?'; $p[] = $f['entity']; }
        if ($f['id'] !== '') { $w[] = 'a.entity_id = ?'; $p[] = $f['id']; }
        if ($f['ip'] !== '') { $w[] = 'a.ip_address = ?'; $p[] = $f['ip']; }
        if ($f['q'] !== '') {
            $w[] = '(a.description LIKE ? OR a.user_email LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($p, $like, $like);
        }
        return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
    }

    public static function changeWhere(array $f): array
    {
        $w = [];
        $p = [];
        self::common($f, 'c', $w, $p);
        if ($f['table'] !== '') { $w[] = 'c.table_name = ?'; $p[] = $f['table']; }
        if ($f['op'] !== '') { $w[] = 'c.operation = ?'; $p[] = $f['op']; }
        if ($f['id'] !== '') { $w[] = 'c.record_id = ?'; $p[] = $f['id']; }
        return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
    }

    public static function activity(array $f, int $limit, int $offset): array
    {
        [$where, $p] = self::activityWhere($f);
        return DB::all("SELECT a.*, u.full_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id {$where}
                        ORDER BY a.id DESC LIMIT ? OFFSET ?", [...$p, $limit, $offset]);
    }

    public static function changes(array $f, int $limit, int $offset): array
    {
        [$where, $p] = self::changeWhere($f);
        return DB::all("SELECT c.*, u.full_name, u.email FROM db_change_logs c LEFT JOIN users u ON u.id = c.user_id {$where}
                        ORDER BY c.id DESC LIMIT ? OFFSET ?", [...$p, $limit, $offset]);
    }

    public static function count(string $table, string $alias, string $where, array $p): int
    {
        return (int) DB::value("SELECT COUNT(*) FROM {$table} {$alias} {$where}", $p);
    }

    /** Fields that differ between old and new (all fields for create/delete). */
    public static function diff(array $row): array
    {
        $old = $row['old_values'] ? (json_decode($row['old_values'], true) ?: []) : [];
        $new = $row['new_values'] ? (json_decode($row['new_values'], true) ?: []) : [];
        $out = [];
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $k) {
            $o = $old[$k] ?? null;
            $n = $new[$k] ?? null;
            if ($row['operation'] !== 'UPDATE' || $o !== $n) {
                $out[$k] = ['old' => $o, 'new' => $n];
            }
        }
        return $out;
    }

    /** Distinct actions grouped by prefix (auth, ticket, user, …) for the filter. */
    public static function actionGroups(): array
    {
        $groups = [];
        foreach (DB::run('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(\PDO::FETCH_COLUMN) as $a) {
            $groups[explode('.', $a)[0]][] = $a;
        }
        return $groups;
    }

    private static function common(array $f, string $alias, array &$w, array &$p): void
    {
        if ($f['from'] !== '') { $w[] = "{$alias}.created_at >= ?"; $p[] = $f['from'] . ' 00:00:00'; }
        if ($f['to'] !== '') { $w[] = "{$alias}.created_at < ? + INTERVAL 1 DAY"; $p[] = $f['to'] . ' 00:00:00'; }
        if ($f['user'] === 'system') {
            $w[] = "{$alias}.user_id IS NULL";
        } elseif ((int) $f['user'] > 0) {
            $w[] = "{$alias}.user_id = ?";
            $p[] = (int) $f['user'];
        }
    }

    private static function date(string $v): string
    {
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v ? $v : '';
    }
}
