<?php
/**
 * Print version of the maintenance report (Dompdf). Dompdf runs no JavaScript,
 * so the daily chart becomes a weekly table and bars are drawn as sized cells.
 * Inline styles are fine here: this HTML never reaches a browser.
 * @var array $r
 */
use App\Services\ReportBuilder as RB;
use App\Services\Sla;
use App\Services\TicketMeta;

$s = $r['summary'];
$p = $r['previous'];
$pct = static fn ($v) => $v === null ? '—' : $v . '%';
$bar = static fn (int $n, int $max) => '<div style="background:#ededed;height:6px;width:100%"><div style="background:#17449e;height:6px;width:'
    . ($max > 0 ? round($n / $max * 100) : 0) . '%"></div></div>';
$maxCat = max(1, ...array_map(static fn ($c) => (int) $c['opened'], $r['byCategory']));
$maxIssue = max(1, ...array_map(static fn ($c) => (int) $c['n'], $r['topIssues'] ?: [['n' => 1]]));
?>
<div style="background:#eaf2fd;border-left:3px solid #4d93e9;padding:8px 10px;font-size:9px;line-height:1.5"><?= e($r['headline']) ?></div>

<h2>Key figures</h2>
<table class="data">
    <thead><tr><th>Measure</th><th class="num"><?= e($r['period']) ?></th><th class="num"><?= e($r['prevLabel']) ?></th></tr></thead>
    <tbody>
    <?php foreach ([
        ['Tickets opened', $s['opened'], $p['opened']],
        ['Tickets resolved', $s['resolved'], $p['resolved']],
        ['Cancelled by requester', $s['cancelled'], $p['cancelled']],
        ['Fixed on time', $pct($s['sla_pct']), $pct($p['sla_pct'])],
        ['First response on time', $pct($s['response_pct']), $pct($p['response_pct'])],
        ['Average first response', RB::minutes($s['avg_response']), RB::minutes($p['avg_response'])],
        ['Average time to fix (excl. hold)', RB::minutes($s['avg_resolution']), RB::minutes($p['avg_resolution'])],
        ['Still open at period end', $s['backlog'], $p['backlog']],
    ] as [$label, $a, $b]): ?>
        <tr><td><?= e($label) ?></td><td class="num"><strong><?= e((string) $a) ?></strong></td><td class="num"><?= e((string) $b) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Opened and resolved by week</h2>
<table class="data">
    <thead><tr><th>Week</th><th class="num">Opened</th><th class="num">Resolved</th></tr></thead>
    <tbody>
    <?php foreach ($r['weeks'] as $w): ?>
        <tr><td><?= e(date('j M', strtotime($w['from'])) . ' – ' . date('j M', strtotime($w['to']))) ?></td><td class="num"><?= (int) $w['opened'] ?></td><td class="num"><?= (int) $w['resolved'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>By priority</h2>
<table class="data">
    <thead><tr><th>Priority</th><th class="num">Opened</th><th class="num">Resolved</th><th class="num">On time</th><th class="num">Average reply time (goal)</th><th class="num">Average time to fix (goal)</th></tr></thead>
    <tbody>
    <?php foreach ($r['byPriority'] as $x): ?>
        <tr>
            <td><?= e($x['name']) ?></td>
            <td class="num"><?= (int) $x['opened'] ?></td>
            <td class="num"><?= (int) $x['resolved'] ?></td>
            <td class="num"><strong><?= $pct($x['sla_pct']) ?></strong></td>
            <td class="num"><?= e(RB::minutes($x['avg_response'] === null ? null : (int) round((float) $x['avg_response']))) ?> (<?= e(Sla::humanMinutes((int) $x['response_minutes'])) ?>)</td>
            <td class="num"><?= e(RB::minutes($x['avg_resolution'] === null ? null : (int) round((float) $x['avg_resolution']))) ?> (<?= e(Sla::humanMinutes((int) $x['resolution_minutes'])) ?>)</td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>By category</h2>
<table class="data">
    <thead><tr><th style="width:22%">Category</th><th style="width:38%">Opened</th><th class="num">Opened</th><th class="num">Resolved</th><th class="num">On time</th><th class="num">Average time to fix</th></tr></thead>
    <tbody>
    <?php foreach ($r['byCategory'] as $x): ?>
        <tr>
            <td><?= e($x['name']) ?></td><td><?= $bar((int) $x['opened'], $maxCat) ?></td>
            <td class="num"><?= (int) $x['opened'] ?></td><td class="num"><?= (int) $x['resolved'] ?></td>
            <td class="num"><?= $pct($x['sla_pct']) ?></td>
            <td class="num"><?= e(RB::minutes($x['avg_resolution'] === null ? null : (int) round((float) $x['avg_resolution']))) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Top recurring issues</h2>
<table class="data">
    <thead><tr><th style="width:4%">#</th><th style="width:36%">Issue</th><th style="width:36%"></th><th class="num">Tickets</th><th class="num">Departments</th></tr></thead>
    <tbody>
    <?php foreach ($r['topIssues'] as $i => $x): ?>
        <tr><td><?= $i + 1 ?></td><td><?= e($x['type']) ?> <span style="color:#666666">(<?= e($x['category']) ?>)</span></td><td><?= $bar((int) $x['n'], $maxIssue) ?></td><td class="num"><?= (int) $x['n'] ?></td><td class="num"><?= (int) $x['departments'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$r['topIssues']): ?><tr><td colspan="5">No tickets in this period.</td></tr><?php endif; ?>
    </tbody>
</table>
<?php if ($r['topLocations']): ?>
    <p style="margin:6px 0 0">Places reporting repeatedly:
        <?= e(implode('; ', array_map(static fn ($x) => $x['department'] . ', ' . $x['location'] . ' (' . $x['n'] . ')', $r['topLocations']))) ?></p>
<?php endif; ?>

<h2>By department</h2>
<table class="data">
    <thead><tr><th>Department</th><th class="num">Opened</th><th class="num">Resolved</th><th class="num">Open at period end</th></tr></thead>
    <tbody>
    <?php foreach ($r['byDepartment'] as $x): ?>
        <tr><td><?= e($x['name']) ?></td><td class="num"><?= (int) $x['opened'] ?></td><td class="num"><?= (int) $x['resolved'] ?></td><td class="num"><?= (int) $x['backlog'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$r['byDepartment']): ?><tr><td colspan="4">No tickets in this period.</td></tr><?php endif; ?>
    </tbody>
</table>

<h2>Technician workload</h2>
<table class="data">
    <thead><tr><th>Technician</th><th class="num">Resolved</th><th class="num">On time</th><th class="num">Average time to fix</th><th class="num">Open now</th></tr></thead>
    <tbody>
    <?php foreach ($r['technicians'] as $x): ?>
        <tr><td><?= e($x['full_name']) ?></td><td class="num"><?= (int) $x['resolved'] ?></td><td class="num"><?= $pct($x['sla_pct']) ?></td>
            <td class="num"><?= e(RB::minutes($x['avg_resolution'] === null ? null : (int) round((float) $x['avg_resolution']))) ?></td><td class="num"><?= (int) $x['open_now'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Still open today (<?= (int) $r['backlog']['total'] ?>)</h2>
<table class="data">
    <thead><tr><?php foreach ($r['backlog']['ages'] as $label => $n): ?><th class="num"><?= e($label) ?></th><?php endforeach; ?></tr></thead>
    <tbody><tr><?php foreach ($r['backlog']['ages'] as $n): ?><td class="num"><strong><?= (int) $n ?></strong></td><?php endforeach; ?></tr></tbody>
</table>
<?php if ($r['backlog']['oldest']): ?>
    <table class="data" style="margin-top:6px">
        <thead><tr><th>Ticket</th><th>Summary</th><th>Priority</th><th>Status</th><th>Department</th><th>Assigned to</th><th class="num">Open for</th></tr></thead>
        <tbody>
        <?php foreach ($r['backlog']['oldest'] as $t): ?>
            <tr><td><?= e($t['ref']) ?></td><td><?= e($t['title']) ?></td><td><?= e($t['priority_name']) ?></td><td><?= e(TicketMeta::STATUSES[$t['status']]['label']) ?></td>
                <td><?= e($t['department_name']) ?></td><td><?= e($t['assignee_name'] ?? 'Unassigned') ?></td><td class="num"><?= e(Sla::duration(time() - strtotime($t['created_at']))) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p style="margin-top:12px;color:#666666">Definitions: Opened = reported in the period. Resolved = marked resolved in the period. Time to fix excludes time on hold.
    On time = resolved within the time set for its priority. Still open at period end = reported before the period ended and not resolved or closed by then.</p>
