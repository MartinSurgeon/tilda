<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Request;

/**
 * Monthly maintenance report. One source of numbers for the page, the PDF and
 * the CSV, so they can never disagree.
 *
 * Definitions (also shown on the report):
 *  - Opened: created in the period. Resolved: resolution recorded in the period.
 *  - Response time: report → first IT action. Resolution time: report → resolved,
 *    minus time on hold.
 *  - SLA met: resolved by its resolution target (hold time excluded).
 *  - Backlog: reported before the period ended and not yet resolved or closed by then.
 */
final class ReportBuilder
{
    /** Filters from the query string, validated. */
    public static function filters(): array
    {
        $month = (string) Request::query('month', '');
        $from = (string) Request::query('from', '');
        $to = (string) Request::query('to', '');

        $valid = static fn (string $d) => ($x = \DateTime::createFromFormat('!Y-m-d', $d)) && $x->format('Y-m-d') === $d;
        if ($from !== '' && $to !== '' && $valid($from) && $valid($to) && $from <= $to
            && (strtotime($to) - strtotime($from)) <= 366 * 86400) {
            $mode = 'range';
            $start = $from . ' 00:00:00';
            $end = date('Y-m-d 00:00:00', strtotime($to . ' +1 day'));
        } else {
            $mode = 'month';
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                $month = date('Y-m');
            }
            $start = $month . '-01 00:00:00';
            $end = date('Y-m-01 00:00:00', strtotime($start . ' +1 month'));
            $from = substr($start, 0, 10);
            $to = date('Y-m-d', strtotime($end . ' -1 day'));
        }
        $status = (string) Request::query('status', '');

        return [
            'mode'       => $mode,
            'month'      => $mode === 'month' ? substr($start, 0, 7) : '',
            'from'       => $from,
            'to'         => $to,
            'start'      => $start,
            'end'        => $end,
            'department' => (int) Request::query('department', 0),
            'category'   => (int) Request::query('category', 0),
            'priority'   => (int) Request::query('priority', 0),
            'status'     => isset(TicketMeta::STATUSES[$status]) ? $status : '',
            'technician' => (int) Request::query('technician', 0),
        ];
    }

    /** Query-string form of the filters (for links and exports). */
    public static function query(array $f): array
    {
        $q = $f['mode'] === 'month' ? ['month' => $f['month']] : ['from' => $f['from'], 'to' => $f['to']];
        foreach (['department', 'category', 'priority', 'status', 'technician'] as $k) {
            if (!empty($f[$k])) {
                $q[$k] = $f[$k];
            }
        }
        return $q;
    }

    public static function periodLabel(array $f): string
    {
        return $f['mode'] === 'month'
            ? date('F Y', strtotime($f['start']))
            : date('j M Y', strtotime($f['from'])) . ' to ' . date('j M Y', strtotime($f['to']));
    }

    public static function build(array $f): array
    {
        [$scope, $sp] = self::scope($f);
        $prev = self::previous($f);

        $r = [
            'filters' => $f,
            'period'  => self::periodLabel($f),
            'summary' => self::summary($f, $scope, $sp),
            'previous'=> self::summary($prev, $scope, $sp),
            'prevLabel' => self::periodLabel($prev),
        ];
        $r['trend'] = self::trend($f, $scope, $sp);
        $r['weeks'] = self::weeks($r['trend']);
        $r['byPriority'] = self::byPriority($f, $scope, $sp);
        $r['byCategory'] = self::byCategory($f, $scope, $sp);
        $r['byDepartment'] = self::byDepartment($f, $scope, $sp);
        $r['topIssues'] = self::topIssues($f, $scope, $sp);
        $r['topLocations'] = self::topLocations($f, $scope, $sp);
        $r['technicians'] = self::technicians($f, $scope, $sp);
        $r['backlog'] = self::backlog($scope, $sp);
        $r['headline'] = self::headline($r);
        return $r;
    }

    // ------------------------------------------------------------------

    /** WHERE fragment (alias t) for the non-period filters. */
    private static function scope(array $f): array
    {
        $w = ['1 = 1'];
        $p = [];
        foreach (['department' => 't.department_id', 'category' => 't.category_id', 'priority' => 't.priority_id', 'technician' => 't.assignee_id'] as $k => $col) {
            if (!empty($f[$k])) {
                $w[] = "{$col} = ?";
                $p[] = (int) $f[$k];
            }
        }
        if ($f['status'] !== '') {
            $w[] = 't.status = ?';
            $p[] = $f['status'];
        }
        return [implode(' AND ', $w), $p];
    }

    /** The same-length period immediately before (the previous month in month mode). */
    private static function previous(array $f): array
    {
        if ($f['mode'] === 'month') {
            $start = date('Y-m-01 00:00:00', strtotime($f['start'] . ' -1 month'));
            return ['mode' => 'month', 'month' => substr($start, 0, 7), 'start' => $start, 'end' => $f['start'],
                    'from' => substr($start, 0, 10), 'to' => date('Y-m-d', strtotime($f['start'] . ' -1 day'))] + $f;
        }
        $len = strtotime($f['end']) - strtotime($f['start']);
        $start = date('Y-m-d H:i:s', strtotime($f['start']) - $len);
        return ['mode' => 'range', 'start' => $start, 'end' => $f['start'], 'from' => substr($start, 0, 10),
                'to' => date('Y-m-d', strtotime($f['start'] . ' -1 day'))] + $f;
    }

    private static function summary(array $f, string $scope, array $sp): array
    {
        $s = $f['start'];
        $e = $f['end'];
        $now = date('Y-m-d H:i:s');
        $row = DB::one(
            "SELECT
                SUM(t.created_at >= ? AND t.created_at < ?) AS opened,
                SUM(t.resolved_at >= ? AND t.resolved_at < ?) AS resolved,
                SUM(t.closed_at >= ? AND t.closed_at < ? AND t.resolved_at IS NULL) AS cancelled,
                AVG(CASE WHEN t.created_at >= ? AND t.created_at < ? AND t.first_response_at IS NOT NULL
                         THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.first_response_at) END) AS avg_response,
                SUM(t.created_at >= ? AND t.created_at < ? AND t.first_response_at IS NOT NULL AND t.first_response_at <= t.response_due_at) AS response_met,
                SUM(t.created_at >= ? AND t.created_at < ? AND (t.first_response_at IS NOT NULL OR t.response_due_at < LEAST(?, ?))) AS response_due,
                AVG(CASE WHEN t.resolved_at >= ? AND t.resolved_at < ?
                         THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at) - t.hold_minutes) END) AS avg_resolution,
                SUM(t.resolved_at >= ? AND t.resolved_at < ? AND t.resolved_at <= t.resolve_due_at) AS sla_met,
                SUM(t.created_at < ? AND (t.resolved_at IS NULL OR t.resolved_at >= ?) AND (t.closed_at IS NULL OR t.closed_at >= ?)) AS backlog,
                SUM(t.status IN ('open','assigned','in_progress','reopened') AND t.resolve_due_at < ?) AS overdue_now
             FROM tickets t WHERE {$scope}",
            [$s, $e, $s, $e, $s, $e, $s, $e, $s, $e, $s, $e, $now, $e, $s, $e, $s, $e, $e, $e, $e, $now, ...$sp]
        ) ?? [];

        $opened = (int) $row['opened'];
        $resolved = (int) $row['resolved'];
        return [
            'opened'         => $opened,
            'resolved'       => $resolved,
            'cancelled'      => (int) $row['cancelled'],
            'avg_response'   => $row['avg_response'] !== null ? (int) round((float) $row['avg_response']) : null,
            'response_pct'   => (int) $row['response_due'] > 0 ? (int) round($row['response_met'] / $row['response_due'] * 100) : null,
            'avg_resolution' => $row['avg_resolution'] !== null ? (int) round((float) $row['avg_resolution']) : null,
            'sla_pct'        => $resolved > 0 ? (int) round($row['sla_met'] / $resolved * 100) : null,
            'sla_met'        => (int) $row['sla_met'],
            'backlog'        => (int) $row['backlog'],
            'overdue_now'    => (int) $row['overdue_now'],
        ];
    }

    /** Opened vs resolved per day. */
    private static function trend(array $f, string $scope, array $sp): array
    {
        $days = [];
        for ($d = strtotime($f['start']); $d < strtotime($f['end']); $d = strtotime('+1 day', $d)) {
            $days[date('Y-m-d', $d)] = ['opened' => 0, 'resolved' => 0];
        }
        foreach (DB::all("SELECT DATE(t.created_at) d, COUNT(*) n FROM tickets t WHERE t.created_at >= ? AND t.created_at < ? AND {$scope} GROUP BY d",
            [$f['start'], $f['end'], ...$sp]) as $r) {
            $days[$r['d']]['opened'] = (int) $r['n'];
        }
        foreach (DB::all("SELECT DATE(t.resolved_at) d, COUNT(*) n FROM tickets t WHERE t.resolved_at >= ? AND t.resolved_at < ? AND {$scope} GROUP BY d",
            [$f['start'], $f['end'], ...$sp]) as $r) {
            $days[$r['d']]['resolved'] = (int) $r['n'];
        }
        return $days;
    }

    /** Weekly roll-up of the daily trend (for print, where charts are not interactive). */
    private static function weeks(array $trend): array
    {
        $weeks = [];
        foreach ($trend as $day => $v) {
            $monday = date('Y-m-d', strtotime('monday this week', strtotime($day)));
            $key = $monday < array_key_first($trend) ? array_key_first($trend) : $monday;
            $weeks[$key] ??= ['from' => $day, 'to' => $day, 'opened' => 0, 'resolved' => 0];
            $weeks[$key]['to'] = $day;
            $weeks[$key]['opened'] += $v['opened'];
            $weeks[$key]['resolved'] += $v['resolved'];
        }
        return array_values($weeks);
    }

    private static function byPriority(array $f, string $scope, array $sp): array
    {
        $rows = DB::all(
            "SELECT p.id, p.name, p.tone, p.icon, s.response_minutes, s.resolution_minutes,
                    SUM(t.created_at >= ? AND t.created_at < ?) AS opened,
                    SUM(t.resolved_at >= ? AND t.resolved_at < ?) AS resolved,
                    SUM(t.resolved_at >= ? AND t.resolved_at < ? AND t.resolved_at <= t.resolve_due_at) AS sla_met,
                    AVG(CASE WHEN t.created_at >= ? AND t.created_at < ? AND t.first_response_at IS NOT NULL
                             THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.first_response_at) END) AS avg_response,
                    AVG(CASE WHEN t.resolved_at >= ? AND t.resolved_at < ?
                             THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at) - t.hold_minutes) END) AS avg_resolution
             FROM priorities p
             LEFT JOIN sla_policies s ON s.priority_id = p.id
             LEFT JOIN tickets t ON t.priority_id = p.id AND {$scope}
             GROUP BY p.id ORDER BY p.sort_order",
            [$f['start'], $f['end'], $f['start'], $f['end'], $f['start'], $f['end'], $f['start'], $f['end'], $f['start'], $f['end'], ...$sp]
        );
        return array_map(static fn ($r) => $r + [
            'sla_pct' => (int) $r['resolved'] > 0 ? (int) round($r['sla_met'] / $r['resolved'] * 100) : null,
        ], $rows);
    }

    private static function byCategory(array $f, string $scope, array $sp): array
    {
        $rows = DB::all(
            "SELECT c.id, c.name, c.icon,
                    SUM(t.created_at >= ? AND t.created_at < ?) AS opened,
                    SUM(t.resolved_at >= ? AND t.resolved_at < ?) AS resolved,
                    SUM(t.resolved_at >= ? AND t.resolved_at < ? AND t.resolved_at <= t.resolve_due_at) AS sla_met,
                    AVG(CASE WHEN t.resolved_at >= ? AND t.resolved_at < ?
                             THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at) - t.hold_minutes) END) AS avg_resolution
             FROM categories c
             LEFT JOIN tickets t ON t.category_id = c.id AND {$scope}
             WHERE c.parent_id IS NULL
             GROUP BY c.id ORDER BY opened DESC, c.sort_order",
            [$f['start'], $f['end'], $f['start'], $f['end'], $f['start'], $f['end'], $f['start'], $f['end'], ...$sp]
        );
        return array_map(static fn ($r) => $r + [
            'sla_pct' => (int) $r['resolved'] > 0 ? (int) round($r['sla_met'] / $r['resolved'] * 100) : null,
        ], $rows);
    }

    private static function byDepartment(array $f, string $scope, array $sp): array
    {
        return DB::all(
            "SELECT d.name,
                    SUM(t.created_at >= ? AND t.created_at < ?) AS opened,
                    SUM(t.resolved_at >= ? AND t.resolved_at < ?) AS resolved,
                    SUM(t.created_at < ? AND (t.resolved_at IS NULL OR t.resolved_at >= ?) AND (t.closed_at IS NULL OR t.closed_at >= ?)) AS backlog
             FROM departments d
             JOIN tickets t ON t.department_id = d.id AND {$scope}
             GROUP BY d.id
             HAVING opened > 0 OR resolved > 0 OR backlog > 0
             ORDER BY opened DESC, d.name",
            [$f['start'], $f['end'], $f['start'], $f['end'], $f['end'], $f['end'], $f['end'], ...$sp]
        );
    }

    /** Recurring problems: the most-reported category › type combinations. */
    private static function topIssues(array $f, string $scope, array $sp): array
    {
        return DB::all(
            "SELECT c.name AS category, COALESCE(sc.name, 'Not specified') AS type, COUNT(*) AS n,
                    COUNT(DISTINCT t.department_id) AS departments
             FROM tickets t JOIN categories c ON c.id = t.category_id LEFT JOIN categories sc ON sc.id = t.subcategory_id
             WHERE t.created_at >= ? AND t.created_at < ? AND {$scope}
             GROUP BY t.category_id, t.subcategory_id ORDER BY n DESC LIMIT 8",
            [$f['start'], $f['end'], ...$sp]
        );
    }

    /** Places that keep reporting problems (useful for planned maintenance). */
    private static function topLocations(array $f, string $scope, array $sp): array
    {
        return DB::all(
            "SELECT d.name AS department, t.location, COUNT(*) AS n
             FROM tickets t JOIN departments d ON d.id = t.department_id
             WHERE t.created_at >= ? AND t.created_at < ? AND t.location <> '' AND {$scope}
             GROUP BY t.department_id, t.location HAVING n > 1 ORDER BY n DESC LIMIT 5",
            [$f['start'], $f['end'], ...$sp]
        );
    }

    private static function technicians(array $f, string $scope, array $sp): array
    {
        $rows = DB::all(
            "SELECT u.id, u.full_name,
                    SUM(t.resolved_at >= ? AND t.resolved_at < ?) AS resolved,
                    SUM(t.resolved_at >= ? AND t.resolved_at < ? AND t.resolved_at <= t.resolve_due_at) AS sla_met,
                    AVG(CASE WHEN t.resolved_at >= ? AND t.resolved_at < ?
                             THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at) - t.hold_minutes) END) AS avg_resolution,
                    SUM(t.status IN ('assigned','in_progress','on_hold','reopened')) AS open_now
             FROM users u
             LEFT JOIN tickets t ON t.assignee_id = u.id AND {$scope}
             WHERE u.role_id IN (SELECT rp.role_id FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE p.slug = 'ticket.work')
               AND (u.is_active = 1 OR t.id IS NOT NULL)
             GROUP BY u.id ORDER BY resolved DESC, u.full_name",
            [$f['start'], $f['end'], $f['start'], $f['end'], $f['start'], $f['end'], ...$sp]
        );
        return array_map(static fn ($r) => $r + [
            'sla_pct' => (int) $r['resolved'] > 0 ? (int) round($r['sla_met'] / $r['resolved'] * 100) : null,
        ], $rows);
    }

    /** What is still open now, by age, plus the oldest items. */
    private static function backlog(string $scope, array $sp): array
    {
        $ages = DB::one(
            "SELECT SUM(TIMESTAMPDIFF(HOUR, t.created_at, NOW()) < 24) AS d1,
                    SUM(TIMESTAMPDIFF(HOUR, t.created_at, NOW()) BETWEEN 24 AND 71) AS d3,
                    SUM(TIMESTAMPDIFF(HOUR, t.created_at, NOW()) BETWEEN 72 AND 167) AS d7,
                    SUM(TIMESTAMPDIFF(HOUR, t.created_at, NOW()) >= 168) AS older,
                    COUNT(*) AS total
             FROM tickets t WHERE t.status IN ('open','assigned','in_progress','on_hold','reopened') AND {$scope}",
            $sp
        ) ?? [];
        $oldest = DB::all(
            "SELECT t.id, t.ref, t.title, t.status, t.created_at, p.name AS priority_name, p.tone, p.icon AS priority_icon,
                    d.name AS department_name, a.full_name AS assignee_name
             FROM tickets t JOIN priorities p ON p.id = t.priority_id JOIN departments d ON d.id = t.department_id
             LEFT JOIN users a ON a.id = t.assignee_id
             WHERE t.status IN ('open','assigned','in_progress','on_hold','reopened') AND {$scope}
             ORDER BY t.created_at LIMIT 10",
            $sp
        );
        return [
            'ages' => [
                'Under 1 day' => (int) ($ages['d1'] ?? 0),
                '1–3 days'    => (int) ($ages['d3'] ?? 0),
                '3–7 days'    => (int) ($ages['d7'] ?? 0),
                'Over 7 days' => (int) ($ages['older'] ?? 0),
            ],
            'total'  => (int) ($ages['total'] ?? 0),
            'oldest' => $oldest,
        ];
    }

    /** One plain-language paragraph for busy readers. */
    private static function headline(array $r): string
    {
        $s = $r['summary'];
        $p = $r['previous'];
        $period = $r['filters']['mode'] === 'month' ? 'In ' . $r['period'] : 'In this period';
        if ($s['opened'] === 0 && $s['resolved'] === 0) {
            return "{$period}, no tickets were opened or resolved.";
        }
        $text = "{$period}, staff reported {$s['opened']} " . ($s['opened'] === 1 ? 'problem' : 'problems')
            . " and IT resolved {$s['resolved']}.";
        if ($s['sla_pct'] !== null) {
            $text .= " {$s['sla_pct']}% were fixed within their target time";
            if ($p['sla_pct'] !== null && $p['sla_pct'] !== $s['sla_pct']) {
                $diff = $s['sla_pct'] - $p['sla_pct'];
                $text .= ' (' . ($diff > 0 ? 'up ' : 'down ') . abs($diff) . (abs($diff) === 1 ? ' point' : ' points') . ' on ' . $r['prevLabel'] . ')';
            }
            $text .= '.';
        }
        if ($r['topIssues']) {
            $t = $r['topIssues'][0];
            $text .= " The most common problem was {$t['category']} › {$t['type']} ({$t['n']}).";
        }
        $text .= " {$s['backlog']} " . ($s['backlog'] === 1 ? 'ticket was' : 'tickets were') . ' still open at the end of the period.';
        return $text;
    }

    /** "1 h 25 min" / "—" for minute values. */
    public static function minutes(?int $m): string
    {
        return $m === null ? '—' : Sla::duration($m * 60);
    }
}
