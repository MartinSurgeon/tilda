<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\View;
use App\Models\Lookup;
use App\Services\AuditLogger;
use App\Services\Export;
use App\Services\ReportBuilder;
use App\Services\TicketMeta;

final class ReportController
{
    public function index(): void
    {
        $f = ReportBuilder::filters();
        $report = ReportBuilder::build($f);
        if (!Request::isBackground()) {
            AuditLogger::log('report.viewed', 'report', 'monthly', 'Viewed maintenance report: ' . $report['period'],
                ['filters' => ReportBuilder::query($f)]);
        }

        View::show('reports/index', [
            'title'       => 'Reports',
            'r'           => $report,
            'f'           => $f,
            'departments' => Lookup::departments(false),
            'categories'  => Lookup::categories(false),
            'priorities'  => Lookup::priorities(),
            'technicians' => Lookup::technicians(),
        ]);
    }

    public function export(): void
    {
        $f = ReportBuilder::filters();
        $format = Request::query('format') === 'csv' ? 'csv' : 'pdf';
        $r = ReportBuilder::build($f);
        $slug = $f['mode'] === 'month' ? $f['month'] : $f['from'] . '_to_' . $f['to'];

        AuditLogger::log('report.exported', 'report', 'monthly', 'Exported maintenance report as ' . strtoupper($format) . ': ' . $r['period'],
            ['filters' => ReportBuilder::query($f)]);

        if ($format === 'csv') {
            Export::csv("ruma-maintenance-report-{$slug}.csv", ['Section', 'Item', 'Value 1', 'Value 2', 'Value 3', 'Value 4', 'Value 5'], self::csvRows($r));
        }

        $body = View::partial('pdf/report', ['r' => $r]);
        Export::document("ruma-maintenance-report-{$slug}.pdf", 'Monthly maintenance report', self::metaLines($r), $body, 'portrait');
    }

    /** Filter summary printed under the PDF title. */
    private static function metaLines(array $r): array
    {
        $f = $r['filters'];
        $lines = ['Period' => $r['period'], 'Compared with' => $r['prevLabel']];
        $names = [
            'department' => ['departments', 'Department'], 'category' => ['categories', 'Category'],
            'priority' => ['priorities', 'Priority'], 'technician' => ['users', 'Technician'],
        ];
        foreach ($names as $key => [$table, $label]) {
            if (!empty($f[$key])) {
                $col = $table === 'users' ? 'full_name' : 'name';
                $lines[$label] = (string) \App\Core\DB::value("SELECT {$col} FROM {$table} WHERE id = ?", [$f[$key]]);
            }
        }
        if ($f['status'] !== '') {
            $lines['Status now'] = TicketMeta::STATUSES[$f['status']]['label'];
        }
        return $lines;
    }

    /** Every section, flattened into one sheet with a section column. */
    private static function csvRows(array $r): \Generator
    {
        $s = $r['summary'];
        $p = $r['previous'];
        $pct = static fn ($v) => $v === null ? '' : $v . '%';
        yield ['Report', 'Period', $r['period'], 'Compared with', $r['prevLabel']];
        yield ['Report', 'Summary', $r['headline']];
        yield ['Summary', 'Metric', 'This period', 'Previous period'];
        foreach ([
            ['Tickets opened', $s['opened'], $p['opened']],
            ['Tickets resolved', $s['resolved'], $p['resolved']],
            ['Cancelled by requester', $s['cancelled'], $p['cancelled']],
            ['Average first response (minutes)', $s['avg_response'], $p['avg_response']],
            ['First response on time', $pct($s['response_pct']), $pct($p['response_pct'])],
            ['Average resolution (minutes, excl. hold)', $s['avg_resolution'], $p['avg_resolution']],
            ['Resolved within SLA', $pct($s['sla_pct']), $pct($p['sla_pct'])],
            ['Open backlog at period end', $s['backlog'], $p['backlog']],
        ] as $row) {
            yield ['Summary', ...$row];
        }
        yield ['By day', 'Date', 'Opened', 'Resolved'];
        foreach ($r['trend'] as $day => $v) {
            yield ['By day', $day, $v['opened'], $v['resolved']];
        }
        yield ['By priority', 'Priority', 'Opened', 'Resolved', 'Within SLA', 'Avg response (min)', 'Avg resolution (min)'];
        foreach ($r['byPriority'] as $x) {
            yield ['By priority', $x['name'], (int) $x['opened'], (int) $x['resolved'], $pct($x['sla_pct']),
                   $x['avg_response'] === null ? '' : (int) round((float) $x['avg_response']),
                   $x['avg_resolution'] === null ? '' : (int) round((float) $x['avg_resolution'])];
        }
        yield ['By category', 'Category', 'Opened', 'Resolved', 'Within SLA', 'Avg resolution (min)'];
        foreach ($r['byCategory'] as $x) {
            yield ['By category', $x['name'], (int) $x['opened'], (int) $x['resolved'], $pct($x['sla_pct']),
                   $x['avg_resolution'] === null ? '' : (int) round((float) $x['avg_resolution'])];
        }
        yield ['By department', 'Department', 'Opened', 'Resolved', 'Open at period end'];
        foreach ($r['byDepartment'] as $x) {
            yield ['By department', $x['name'], (int) $x['opened'], (int) $x['resolved'], (int) $x['backlog']];
        }
        yield ['Top recurring issues', 'Issue', 'Tickets', 'Departments affected'];
        foreach ($r['topIssues'] as $x) {
            yield ['Top recurring issues', $x['category'] . ' > ' . $x['type'], (int) $x['n'], (int) $x['departments']];
        }
        yield ['Repeat locations', 'Location', 'Tickets'];
        foreach ($r['topLocations'] as $x) {
            yield ['Repeat locations', $x['department'] . ', ' . $x['location'], (int) $x['n']];
        }
        yield ['Technicians', 'Technician', 'Resolved', 'Within SLA', 'Avg resolution (min)', 'Open now'];
        foreach ($r['technicians'] as $x) {
            yield ['Technicians', $x['full_name'], (int) $x['resolved'], $pct($x['sla_pct']),
                   $x['avg_resolution'] === null ? '' : (int) round((float) $x['avg_resolution']), (int) $x['open_now']];
        }
        yield ['Open backlog (now)', 'Age', 'Tickets'];
        foreach ($r['backlog']['ages'] as $label => $n) {
            yield ['Open backlog (now)', $label, $n];
        }
        foreach ($r['backlog']['oldest'] as $x) {
            yield ['Oldest open', $x['ref'], $x['title'], $x['priority_name'], TicketMeta::STATUSES[$x['status']]['label'], $x['created_at'], $x['assignee_name'] ?? 'Unassigned'];
        }
    }
}
