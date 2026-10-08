<?php
/**
 * @var array $r @var array $f @var array $departments @var array $categories @var array $priorities @var array $technicians
 */
use App\Core\View;
use App\Services\ReportBuilder as RB;
use App\Services\TicketMeta;

$s = $r['summary'];
$p = $r['previous'];
$q = RB::query($f);
$advanced = count(array_filter([$f['department'], $f['category'], $f['priority'], $f['status'], $f['technician']])) + ($f['mode'] === 'range' ? 1 : 0);
$pct = static fn ($v) => $v === null ? '—' : $v . '%';
$kpi = static fn (array $o) => View::partial('pages/reports/_kpi', $o + ['prevLabel' => $r['prevLabel'], 'unit' => '']);
$maxCat = max(1, ...array_map(static fn ($c) => (int) $c['opened'], $r['byCategory']));
$maxIssue = max(1, ...array_map(static fn ($c) => (int) $c['n'], $r['topIssues'] ?: [['n' => 1]]));
?>
<header class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="page-title">Maintenance report</h1>
        <p class="page-subtitle"><?= e($r['period']) ?> · compared with <?= e($r['prevLabel']) ?></p>
    </div>
    <?php if (can('report.export')): ?>
        <div class="flex gap-2">
            <a href="<?= e(url('/reports/export', $q + ['format' => 'pdf'])) ?>" class="btn-primary flex-1 sm:flex-none"><?= icon('download') ?> Download PDF</a>
            <a href="<?= e(url('/reports/export', $q + ['format' => 'csv'])) ?>" class="btn-secondary flex-1 sm:flex-none"><?= icon('file') ?> CSV</a>
        </div>
    <?php endif; ?>
</header>

<form method="get" action="<?= e(url('/reports')) ?>" class="card card-body mb-6 space-y-4" role="search" aria-label="Report period and filters">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="sm:w-56">
            <label for="month" class="label">Month</label>
            <input type="month" id="month" name="month" class="input" value="<?= e($f['month'] ?: date('Y-m')) ?>" max="<?= date('Y-m') ?>">
        </div>
        <button type="submit" class="btn-secondary"><?= icon('refresh') ?> Show report</button>
    </div>
    <details <?= $advanced ? 'open' : '' ?>>
        <summary class="inline-flex min-h-touch cursor-pointer items-center gap-2 text-sm font-semibold text-midnight"><?= icon('filter', 'h-4 w-4') ?> Custom dates and filters<?= $advanced ? " ({$advanced} on)" : '' ?></summary>
        <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="from" class="label">From <span class="label-optional">(replaces month)</span></label>
                <input type="date" id="from" name="from" class="input" value="<?= $f['mode'] === 'range' ? e($f['from']) : '' ?>">
            </div>
            <div>
                <label for="to" class="label">To</label>
                <input type="date" id="to" name="to" class="input" value="<?= $f['mode'] === 'range' ? e($f['to']) : '' ?>">
            </div>
            <?php foreach ([
                ['department', 'Department', $departments, 'name'],
                ['category', 'Category', $categories, 'name'],
                ['priority', 'Priority', $priorities, 'name'],
                ['technician', 'Technician', $technicians, 'full_name'],
            ] as [$key, $label, $list, $col]): ?>
                <div>
                    <label for="r-<?= $key ?>" class="label"><?= e($label) ?></label>
                    <select id="r-<?= $key ?>" name="<?= $key ?>" class="select">
                        <option value="">All</option>
                        <?php foreach ($list as $o): ?>
                            <option value="<?= (int) $o['id'] ?>" <?= (int) $f[$key] === (int) $o['id'] ? 'selected' : '' ?>><?= e($o[$col]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>
            <div>
                <label for="r-status" class="label">Current status</label>
                <select id="r-status" name="status" class="select">
                    <option value="">All</option>
                    <?php foreach (TicketMeta::STATUSES as $k => $m): ?>
                        <option value="<?= e($k) ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($m['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-1">
                <a href="<?= e(url('/reports')) ?>" class="btn-ghost">Reset</a>
                <button type="submit" class="btn-secondary flex-1">Apply</button>
            </div>
        </div>
    </details>
</form>

<div class="space-y-6">

<!-- Most important first: the plain-language summary and the headline numbers. -->
<section class="alert-info" aria-labelledby="summary-title">
    <?= icon('info', 'mt-0.5 h-5 w-5 shrink-0') ?>
    <div>
        <h2 id="summary-title" class="font-semibold text-info-text">Summary</h2>
        <p class="mt-1"><?= e($r['headline']) ?></p>
    </div>
</section>

<section aria-labelledby="kpi-title">
    <h2 id="kpi-title" class="sr-only">Key figures</h2>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
        <?= $kpi(['label' => 'Tickets opened', 'value' => (string) $s['opened'], 'now' => $s['opened'], 'before' => $p['opened'], 'better' => 'none']) ?>
        <?= $kpi(['label' => 'Tickets resolved', 'value' => (string) $s['resolved'], 'now' => $s['resolved'], 'before' => $p['resolved'], 'better' => 'up']) ?>
        <?= $kpi(['label' => 'Fixed on time', 'value' => $pct($s['sla_pct']), 'now' => $s['sla_pct'], 'before' => $p['sla_pct'], 'better' => 'up', 'unit' => ' pts']) ?>
        <?= $kpi(['label' => 'Average first response', 'value' => RB::minutes($s['avg_response']), 'now' => $s['avg_response'], 'before' => $p['avg_response'], 'better' => 'down', 'unit' => ' min']) ?>
        <?= $kpi(['label' => 'Average time to fix', 'value' => RB::minutes($s['avg_resolution']), 'now' => $s['avg_resolution'], 'before' => $p['avg_resolution'], 'better' => 'down', 'unit' => ' min']) ?>
        <?= $kpi(['label' => 'Still open at the end', 'value' => (string) $s['backlog'], 'now' => $s['backlog'], 'before' => $p['backlog'], 'better' => 'down']) ?>
    </div>
</section>

<section class="card" aria-labelledby="trend-title">
    <div class="card-header"><h2 id="trend-title" class="card-title">Opened and resolved each day</h2></div>
    <div class="card-body"><?= View::partial('components/trend-chart', ['trend' => $r['trend'], 'caption' => $r['period']]) ?></div>
</section>

<div class="grid gap-6 *:min-w-0 lg:grid-cols-2">
    <section class="card" aria-labelledby="prio-title">
        <div class="card-header"><h2 id="prio-title" class="card-title">By priority</h2></div>
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="By priority, scrollable table">
            <table class="table">
                <thead><tr>
                    <th scope="col">Priority</th><th scope="col" class="text-right">Opened</th><th scope="col" class="text-right">Resolved</th>
                    <th scope="col" class="text-right">On time</th><th scope="col" class="hidden text-right sm:table-cell">Average fix time (goal)</th>
                </tr></thead>
                <tbody>
                <?php foreach ($r['byPriority'] as $x): ?>
                    <tr>
                        <th scope="row"><?= TicketMeta::priorityBadge($x['name'], $x['tone'], $x['icon']) ?></th>
                        <td class="text-right"><?= (int) $x['opened'] ?></td>
                        <td class="text-right"><?= (int) $x['resolved'] ?></td>
                        <td class="text-right font-semibold text-ink"><?= $pct($x['sla_pct']) ?></td>
                        <td class="hidden whitespace-nowrap text-right sm:table-cell">
                            <?= e(RB::minutes($x['avg_resolution'] === null ? null : (int) round((float) $x['avg_resolution']))) ?>
                            <span class="text-muted">(<?= e(App\Services\Sla::humanMinutes((int) $x['resolution_minutes'])) ?>)</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" aria-labelledby="cat-title">
        <div class="card-header"><h2 id="cat-title" class="card-title">By category</h2><span class="text-sm text-muted">tickets opened</span></div>
        <ul class="card-body space-y-4" role="list">
            <?php foreach ($r['byCategory'] as $c): $n = (int) $c['opened']; ?>
                <li>
                    <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                        <span class="flex items-center gap-2 font-semibold text-ink"><?= icon($c['icon'], 'h-4 w-4 text-muted') ?><?= e($c['name']) ?></span>
                        <span><strong class="text-ink"><?= $n ?></strong> <span class="text-muted">· <?= (int) $c['resolved'] ?> resolved · on time <?= $pct($c['sla_pct']) ?></span></span>
                    </div>
                    <meter class="meter meter-medium" min="0" max="<?= $maxCat ?>" value="<?= $n ?>" aria-label="<?= e($c['name']) ?>: <?= $n ?> opened"><?= $n ?></meter>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<div class="grid gap-6 *:min-w-0 lg:grid-cols-2">
    <section class="card" aria-labelledby="issues-title">
        <div class="card-header"><h2 id="issues-title" class="card-title">Top recurring issues</h2></div>
        <?php if (!$r['topIssues']): ?>
            <p class="card-body text-sm text-muted">No tickets in this period.</p>
        <?php else: ?>
            <ol class="card-body space-y-4" role="list">
                <?php foreach ($r['topIssues'] as $i => $x): $n = (int) $x['n']; ?>
                    <li>
                        <div class="mb-1 flex items-start justify-between gap-2 text-sm">
                            <span class="text-ink"><span class="text-muted"><?= $i + 1 ?>.</span> <strong><?= e($x['type']) ?></strong> <span class="text-muted">· <?= e($x['category']) ?></span></span>
                            <span class="shrink-0"><strong class="text-ink"><?= $n ?></strong> <span class="text-muted">· <?= (int) $x['departments'] ?> dept<?= (int) $x['departments'] === 1 ? '' : 's' ?></span></span>
                        </div>
                        <meter class="meter meter-medium" min="0" max="<?= $maxIssue ?>" value="<?= $n ?>" aria-label="<?= e($x['type']) ?>: <?= $n ?> tickets"><?= $n ?></meter>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <section class="card" aria-labelledby="dept-title">
        <div class="card-header"><h2 id="dept-title" class="card-title">By department</h2></div>
        <?php if (!$r['byDepartment']): ?>
            <p class="card-body text-sm text-muted">No tickets in this period.</p>
        <?php else: ?>
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="By department, scrollable table">
                <table class="table">
                    <thead><tr><th scope="col">Department</th><th scope="col" class="text-right">Opened</th><th scope="col" class="text-right">Resolved</th><th scope="col" class="text-right">Open at end</th></tr></thead>
                    <tbody>
                    <?php foreach ($r['byDepartment'] as $x): ?>
                        <tr><th scope="row" class="font-semibold text-ink"><?= e($x['name']) ?></th><td class="text-right"><?= (int) $x['opened'] ?></td><td class="text-right"><?= (int) $x['resolved'] ?></td><td class="text-right"><?= (int) $x['backlog'] ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($r['topLocations']): ?>
                <div class="border-t border-line px-4 py-4 sm:px-6">
                    <h3 class="text-sm font-semibold text-ink">Places reporting repeatedly</h3>
                    <ul class="mt-2 space-y-1 text-sm" role="list">
                        <?php foreach ($r['topLocations'] as $x): ?>
                            <li><?= icon('map-pin', 'mr-1 inline h-4 w-4 text-muted') ?><?= e($x['department'] . ', ' . $x['location']) ?> · <strong class="text-ink"><?= (int) $x['n'] ?></strong> tickets</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<section class="card" aria-labelledby="tech-title">
    <div class="card-header"><h2 id="tech-title" class="card-title">Technician workload</h2></div>
    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Technician workload, scrollable table">
        <table class="table">
            <thead><tr>
                <th scope="col">Technician</th><th scope="col" class="text-right">Resolved</th><th scope="col" class="text-right">On time</th>
                <th scope="col" class="hidden text-right sm:table-cell">Avg time to fix</th><th scope="col" class="text-right">Open now</th>
            </tr></thead>
            <tbody>
            <?php foreach ($r['technicians'] as $x): ?>
                <tr>
                    <th scope="row" class="font-semibold text-ink"><?= e($x['full_name']) ?></th>
                    <td class="text-right"><?= (int) $x['resolved'] ?></td>
                    <td class="text-right"><?= $pct($x['sla_pct']) ?></td>
                    <td class="hidden whitespace-nowrap text-right sm:table-cell"><?= e(RB::minutes($x['avg_resolution'] === null ? null : (int) round((float) $x['avg_resolution']))) ?></td>
                    <td class="text-right"><?= (int) $x['open_now'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Last (serial position): what still needs doing. -->
<section class="card" aria-labelledby="backlog-title">
    <div class="card-header"><h2 id="backlog-title" class="card-title">Still open today</h2><span class="text-sm text-muted"><?= (int) $r['backlog']['total'] ?> open</span></div>
    <div class="card-body space-y-5">
        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <?php foreach ($r['backlog']['ages'] as $label => $n): ?>
                <div class="rounded-lg bg-smoke-2 p-3 <?= $label === 'Over 7 days' && $n > 0 ? 'ring-1 ring-danger-fg/40' : '' ?>">
                    <dt class="text-sm text-muted"><?= e($label) ?></dt>
                    <dd class="text-xl font-bold text-ink"><?= (int) $n ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
        <?php if ($r['backlog']['oldest']): ?>
            <div>
                <h3 class="mb-2 text-sm font-semibold text-ink">Oldest open tickets</h3>
                <ul class="divide-y divide-line rounded-lg border border-line" role="list">
                    <?php foreach ($r['backlog']['oldest'] as $t): ?>
                        <li class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:gap-3">
                            <div class="min-w-0 flex-1">
                                <?php if (can('ticket.view_all')): ?>
                                    <a href="<?= e(url('/tickets/' . $t['id'])) ?>" class="inline-flex min-h-touch items-center font-semibold text-ink"><?= e($t['title']) ?></a>
                                <?php else: ?>
                                    <span class="font-semibold text-ink"><?= e($t['title']) ?></span>
                                <?php endif; ?>
                                <p class="text-sm text-muted flex items-center gap-1.5 flex-wrap"><span class="ticket-ref"><?= e($t['ref']) ?></span> <span>·</span> <span><?= e($t['department_name']) ?></span> <span>·</span> <span><?= e($t['assignee_name'] ?? 'Unassigned') ?></span> <span>·</span> <span>open <?= e(App\Services\Sla::duration(time() - strtotime($t['created_at']))) ?></span></p>
                            </div>
                            <div class="flex gap-2"><?= TicketMeta::priorityBadge($t['priority_name'], $t['tone'], $t['priority_icon']) ?><?= TicketMeta::statusBadge($t['status']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</section>

<details class="card card-body text-sm">
    <summary class="inline-flex min-h-touch cursor-pointer items-center font-semibold text-midnight">How these numbers are calculated</summary>
    <ul class="mt-2 list-disc space-y-1 pl-5" role="list">
        <li><strong>Opened</strong>: reported during the period. <strong>Resolved</strong>: marked resolved during the period.</li>
        <li><strong>First response</strong>: time from report to the first action by IT (taking the ticket, a reply or a status change).</li>
        <li><strong>Time to fix</strong>: time from report to resolved, not counting time on hold (for example, waiting for a part).</li>
        <li><strong>On time</strong>: share of fixed tickets that were fixed within the time set for their priority.</li>
        <li><strong>Still open at period end</strong>: reported before the period ended and not resolved or closed by then. Tickets reopened later count by their current state.</li>
        <li>Filters other than dates apply to every section. “Current status” filters by each ticket’s status today.</li>
    </ul>
</details>

</div>
