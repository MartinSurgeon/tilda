<?php
use App\Core\View;
use App\Services\TicketMeta;

/** @var array $sections */
$me = user();
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', $me['full_name'])[0];
$has = static fn (string $s) => in_array($s, $sections, true);
?>
<header class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="page-title text-2xl sm:text-3xl font-extrabold tracking-tight text-ink"><?= e($greeting . ', ' . $firstName) ?></h1>
        <p class="page-subtitle text-sm text-muted font-medium"><?= e($me['role_name']) ?><?= $me['department_name'] ? ' · ' . e($me['department_name']) : '' ?></p>
    </div>
    <div class="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3 py-1 text-xs font-medium text-muted shadow-xs">
        <span class="h-2 w-2 rounded-full bg-green animate-pulse" aria-hidden="true"></span>
        <span data-live-stamp aria-live="polite">Updates automatically</span>
    </div>
</header>

<div class="space-y-8">

<?php if (can('ticket.create') && !can('ticket.view_all') && !$has('overview')): ?>
    <section class="card overflow-hidden border-teal/20 shadow-card" aria-labelledby="report-title">
        <div class="flex flex-col gap-4 bg-brand-linear-1 p-6 text-white sm:flex-row sm:items-center sm:justify-between">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider text-white">
                    <?= icon('shield-check', 'h-3.5 w-3.5') ?> Quick IT Service
                </span>
                <h2 id="report-title" class="mt-2 text-xl font-bold text-white">Something not working?</h2>
                <p class="mt-1 text-sm text-white/90">Report any technical or equipment problem in under a minute. IT will track it in real time.</p>
            </div>
            <a href="<?= e(url('/tickets/create')) ?>" class="btn min-h-touch bg-surface text-teal-darker font-bold shadow-raised hover:bg-teal-tint transition-all shrink-0">
                <?= icon('plus') ?> Report a problem
            </a>
        </div>
    </section>
<?php endif; ?>

<?php if ($has('mine')): ?>
    <!-- Most important for staff: tickets waiting for their confirmation. Wrapper always present so a live refresh can fill it. -->
    <div id="live-awaiting" data-live <?= $awaiting ? "" : "hidden" ?>><?php if ($awaiting): ?>
    <section class="rounded-xl border-2 border-teal/40 bg-teal-tint/40 p-4 sm:p-5 shadow-xs" aria-labelledby="awaiting-title">
        <div class="flex items-start gap-3 sm:gap-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal text-white shadow-xs">
                <?= icon('check-circle', 'h-6 w-6') ?>
            </span>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="awaiting-title" class="text-base font-bold text-teal-darker">
                        IT resolved <?= count($awaiting) === 1 ? 'your issue' : count($awaiting) . ' issues' ?>. Please confirm if it's fixed:
                    </h2>
                    <span class="badge-teal">Action required</span>
                </div>
                <ul class="mt-3 divide-y divide-teal/15 rounded-lg border border-teal/20 bg-surface shadow-xs" role="list">
                    <?php foreach ($awaiting as $a): ?>
                        <li class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <a href="<?= e(url('/tickets/' . $a['id'])) ?>" class="font-bold text-ink hover:text-teal-darker no-underline hover:underline">
                                    <span class="ticket-ref mr-1.5"><?= e($a['ref']) ?></span><?= e($a['title']) ?>
                                </a>
                            </div>
                            <a href="<?= e(url('/tickets/' . $a['id'])) ?>" class="btn-primary text-xs h-8 min-h-0 px-3 self-start sm:self-auto shrink-0">
                                Review &amp; confirm <?= icon('chevron-right', 'h-3.5 w-3.5') ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>
    <?php endif; ?></div>
<?php endif; ?>

<?php if ($has('overview')): $k = $kpi; ?>
    <section id="live-kpi" data-live aria-labelledby="kpi-title">
        <div class="mb-3 flex items-center justify-between">
            <h2 id="kpi-title" class="section-title">Service at a glance</h2>
            <span class="text-xs text-muted font-medium">Real-time telemetry</span>
        </div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <?= View::partial('components/stat', [
                'label' => 'Open tickets',
                'value' => $k['open_now'],
                'icon'  => 'inbox',
                'tone'  => 'bg-info-tint text-info-text',
                'sub'   => 'Active hospital queue',
                'href'  => can('ticket.view_all') ? url('/tickets', ['view' => 'active']) : null
            ]) ?>
            <?= View::partial('components/stat', [
                'label' => 'Waiting for technician',
                'value' => $k['unassigned'],
                'icon'  => 'user-check',
                'tone'  => 'bg-midnight-tint text-midnight',
                'sub'   => 'Unassigned triage backlog',
                'href'  => can('ticket.view_all') ? url('/tickets', ['view' => 'unassigned']) : null
            ]) ?>
            <?= View::partial('components/stat', [
                'label' => 'Overdue (SLA)',
                'value' => $k['overdue'],
                'icon'  => 'alert-triangle',
                'tone'  => (int)$k['overdue'] > 0 ? 'bg-danger-tint text-danger-fg ring-1 ring-danger-fg/30' : 'bg-smoke text-muted',
                'sub'   => (int)$k['overdue'] > 0 ? 'Urgent action required' : 'All within target',
                'href'  => can('ticket.view_all') ? url('/tickets', ['view' => 'overdue']) : null
            ]) ?>
            <?= View::partial('components/stat', [
                'label' => 'Resolved on time this month',
                'value' => $k['sla_month'] === null ? '—' : $k['sla_month'] . '%',
                'icon'  => 'check-circle',
                'tone'  => 'bg-green-tint text-green-text',
                'sub'   => $k['resolved_month'] . ' total resolved',
            ]) ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($has('queue')): ?>
    <section id="live-queue" data-live aria-labelledby="queue-title">
        <div class="mb-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h2 id="queue-title" class="section-title">Needs attention</h2>
                <?php if (!empty($attention)): ?>
                    <span class="inline-flex items-center rounded-full bg-smoke-2 px-2.5 py-0.5 text-xs font-bold tabular-nums text-ink border border-line">
                        <?= count($attention) ?>
                    </span>
                <?php endif; ?>
            </div>
            <a href="<?= e(url('/tickets')) ?>" class="inline-flex min-h-touch items-center gap-1 text-sm font-semibold text-midnight hover:underline">
                Open queue <?= icon('chevron-right', 'h-4 w-4') ?>
            </a>
        </div>
        <?php if (!$has('overview')): $q = $queue; ?>
            <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <?= View::partial('components/stat', ['label' => 'Assigned to me', 'value' => (int) ($q['mine'] ?? 0), 'icon' => 'user-check', 'tone' => 'bg-teal-tint text-teal-darker', 'sub' => 'My active workload', 'href' => url('/tickets', ['view' => 'mine'])]) ?>
                <?= View::partial('components/stat', ['label' => 'Unassigned', 'value' => (int) ($q['unassigned'] ?? 0), 'icon' => 'inbox', 'tone' => 'bg-info-tint text-info-text', 'sub' => 'Ready for pickup', 'href' => url('/tickets', ['view' => 'unassigned'])]) ?>
                <?= View::partial('components/stat', ['label' => 'All open', 'value' => (int) ($q['active'] ?? 0), 'icon' => 'ticket', 'tone' => 'bg-midnight-tint text-midnight', 'sub' => 'Hospital-wide active', 'href' => url('/tickets', ['view' => 'active'])]) ?>
                <?= View::partial('components/stat', ['label' => 'Overdue (SLA)', 'value' => (int) ($q['overdue'] ?? 0), 'icon' => 'alert-triangle', 'tone' => (int)($q['overdue'] ?? 0) > 0 ? 'bg-danger-tint text-danger-fg ring-1 ring-danger-fg/30' : 'bg-smoke text-muted', 'sub' => (int)($q['overdue'] ?? 0) > 0 ? 'Breached resolution targets' : 'None breached', 'href' => url('/tickets', ['view' => 'overdue'])]) ?>
            </div>
        <?php endif; ?>
        <div class="card overflow-hidden">
            <?php if (!$attention): ?>
                <?= View::partial('components/empty-state', ['icon' => 'check-circle', 'title' => 'All clear', 'level' => 3,
                    'text' => 'Nothing is waiting for you or unassigned right now.']) ?>
            <?php else: ?>
                <ul class="divide-y divide-line" role="list" data-kb-list>
                    <?php foreach ($attention as $t): ?>
                        <li data-kb-item>
                            <a href="<?= e(url('/tickets/' . $t['id'])) ?>" class="group flex min-h-touch flex-col gap-2.5 px-4 py-3.5 text-charcoal no-underline hover:bg-smoke-2/70 hover:no-underline transition-colors sm:flex-row sm:items-center sm:px-6">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="ticket-ref">
                                            <?= e($t['ref']) ?>
                                        </span>
                                        <p class="truncate font-bold text-ink group-hover:text-teal-darker transition-colors"><?= e($t['title']) ?></p>
                                    </div>
                                    <p class="mt-1 text-xs text-muted flex items-center gap-1.5 flex-wrap">
                                        <span><?= e($t['department_name']) ?></span>
                                        <span>·</span>
                                        <span class="inline-flex items-center gap-1">
                                            <?= icon('user', 'h-3.5 w-3.5') ?> <?= $t['assignee_id'] ? 'Assigned to you' : '<span class="text-warning-text font-medium">Unassigned</span>' ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 shrink-0">
                                    <?= TicketMeta::priorityBadge($t['priority_name'], $t['tone'], $t['priority_icon']) ?>
                                    <?= TicketMeta::slaBadge($t) ?>
                                    <span class="text-muted transition-transform duration-150 group-hover:translate-x-0.5 group-hover:text-ink hidden sm:inline-block ml-1" aria-hidden="true">
                                        <?= icon('chevron-right', 'h-4 w-4') ?>
                                    </span>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($has('overview')): ?>
    <div id="live-overview" data-live class="grid gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2" aria-labelledby="trend-title">
            <div class="card-header">
                <div>
                    <h2 id="trend-title" class="card-title">Activity trend</h2>
                    <p class="text-xs text-muted mt-0.5">Opened vs resolved issues across the last 14 days</p>
                </div>
            </div>
            <div class="card-body">
                <?= View::partial('components/trend-chart', ['trend' => $trend]) ?>
            </div>
        </section>
        <section class="card" aria-labelledby="prio-title">
            <div class="card-header">
                <div>
                    <h2 id="prio-title" class="card-title">Open by priority</h2>
                    <p class="text-xs text-muted mt-0.5">Distribution across clinical urgency tiers</p>
                </div>
            </div>
            <?php $maxP = max(1, ...array_map(static fn ($p) => (int) $p['total'], $byPriority)); ?>
            <ul class="card-body space-y-4" role="list">
                <?php foreach ($byPriority as $p): $n = (int) $p['total']; ?>
                    <li>
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <?= TicketMeta::priorityBadge($p['name'], $p['tone'], $p['icon']) ?>
                            <span class="inline-flex rounded-full bg-smoke-2 px-2 py-0.5 text-xs font-bold tabular-nums text-ink border border-line"><?= $n ?></span>
                        </div>
                        <meter class="meter meter-<?= e($p['tone']) ?>" min="0" max="<?= $maxP ?>" value="<?= $n ?>" aria-label="<?= e($p['name']) ?>: <?= $n ?> open"><?= $n ?></meter>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card lg:col-span-3" aria-labelledby="workload-title">
            <div class="card-header">
                <div>
                    <h2 id="workload-title" class="card-title">Technician workload</h2>
                    <p class="text-xs text-muted mt-0.5">Current operational capacity and active ticket queue per technician</p>
                </div>
            </div>
            <?php if (!$workload): ?>
                <p class="card-body text-sm text-muted">No technicians registered yet.</p>
            <?php else: ?>
                <div class="hidden overflow-x-auto md:block">
                    <table class="table">
                        <thead><tr>
                            <th scope="col" class="font-semibold text-xs text-muted uppercase tracking-wider">Technician</th>
                            <th scope="col" class="text-right font-semibold text-xs text-muted uppercase tracking-wider">Not started</th>
                            <th scope="col" class="text-right font-semibold text-xs text-muted uppercase tracking-wider">In progress</th>
                            <th scope="col" class="text-right font-semibold text-xs text-muted uppercase tracking-wider">On hold</th>
                            <th scope="col" class="text-right font-semibold text-xs text-muted uppercase tracking-wider">Overdue</th>
                            <th scope="col" class="text-right font-semibold text-xs text-muted uppercase tracking-wider">Resolved this month</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($workload as $w): ?>
                            <tr class="hover:bg-smoke-2/50 transition-colors">
                                <th scope="row" class="font-bold text-ink flex items-center gap-2">
                                    <span class="avatar h-7 w-7 text-xs" aria-hidden="true"><?= e(initials($w['full_name'])) ?></span>
                                    <span><?= e($w['full_name']) ?></span>
                                </th>
                                <td class="text-right tabular-nums text-sm font-semibold"><?= (int) $w['waiting'] ?></td>
                                <td class="text-right tabular-nums text-sm font-semibold"><?= (int) $w['in_progress'] ?></td>
                                <td class="text-right tabular-nums text-sm font-semibold"><?= (int) $w['on_hold'] ?></td>
                                <td class="text-right"><?= (int) $w['overdue'] ? '<span class="badge-danger tabular-nums">' . icon('alert-triangle', 'h-3.5 w-3.5') . (int) $w['overdue'] . '</span>' : '<span class="text-muted tabular-nums">0</span>' ?></td>
                                <td class="text-right tabular-nums font-bold text-green-text"><?= (int) $w['resolved_month'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <ul class="divide-y divide-line md:hidden" role="list">
                    <?php foreach ($workload as $w): ?>
                        <li class="p-4 space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="avatar h-7 w-7 text-xs" aria-hidden="true"><?= e(initials($w['full_name'])) ?></span>
                                <p class="font-bold text-ink"><?= e($w['full_name']) ?></p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
                                <span class="badge-neutral"><?= (int) $w['waiting'] + (int) $w['in_progress'] + (int) $w['on_hold'] ?> active</span>
                                <span><?= (int) $w['in_progress'] ?> in progress</span>
                                <span>·</span>
                                <span><?= (int) $w['on_hold'] ?> on hold</span>
                                <span>·</span>
                                <span class="text-green-text font-bold"><?= (int) $w['resolved_month'] ?> resolved</span>
                                <?php if ((int) $w['overdue']): ?>
                                    <span class="badge-danger"><?= (int) $w['overdue'] ?> overdue</span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
<?php endif; ?>

<?php if ($has('mine')): ?>
    <!-- Managers rarely raise tickets: only show this when they have some. -->
    <section id="live-mine" data-live aria-labelledby="mine-title" <?= $has('overview') && !$myTickets ? 'hidden' : '' ?>>
        <div class="mb-3 flex items-center justify-between">
            <h2 id="mine-title" class="section-title">Tickets I raised</h2>
            <?php if ($myTickets): ?><a href="<?= e(url('/tickets/mine')) ?>" class="inline-flex min-h-touch items-center gap-1 text-sm font-semibold text-midnight hover:underline">View all <?= icon('chevron-right', 'h-4 w-4') ?></a><?php endif; ?>
        </div>
        <div class="card overflow-hidden">
            <?php if (!$myTickets): ?>
                <div class="card-body">
                    <?= View::partial('components/empty-state', [
                        'icon' => 'ticket', 'title' => 'You have not reported any problems', 'level' => 3,
                        'text' => 'When you report a problem, you can follow its live resolution progress here.',
                    ]) ?>
                </div>
            <?php else: ?>
                <ul class="divide-y divide-line" role="list" data-kb-list>
                    <?php foreach ($myTickets as $t): ?>
                        <li data-kb-item>
                            <a href="<?= e(url('/tickets/' . $t['id'])) ?>" class="group flex min-h-touch items-center gap-3 px-4 py-3.5 text-charcoal no-underline hover:bg-smoke-2/70 hover:no-underline transition-colors sm:px-6">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="ticket-ref">
                                            <?= e($t['ref']) ?>
                                        </span>
                                        <p class="truncate font-bold text-ink group-hover:text-teal-darker transition-colors"><?= e($t['title']) ?></p>
                                    </div>
                                    <p class="mt-1 text-xs text-muted flex items-center gap-2 flex-wrap">
                                        <span><?= e($t['assignee_name'] ? $t['assignee_name'] . ' is on it' : 'Waiting for IT assignment') ?></span>
                                        <span>·</span>
                                        <span>Updated <?= e(fmt_relative($t['updated_at'])) ?></span>
                                    </p>
                                </div>
                                <?= TicketMeta::statusBadge($t['status']) ?>
                                <span class="text-muted transition-transform duration-150 group-hover:translate-x-0.5 group-hover:text-ink shrink-0" aria-hidden="true">
                                    <?= icon('chevron-right', 'h-4 w-4') ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($has('audit')): ?>
    <section id="live-audit" data-live aria-labelledby="audit-title">
        <div class="mb-3 flex items-center justify-between">
            <h2 id="audit-title" class="section-title">Audit trail today</h2>
            <a href="<?= e(url('/audit')) ?>" class="inline-flex min-h-touch items-center gap-1 text-sm font-semibold text-midnight hover:underline">Open audit log <?= icon('chevron-right', 'h-4 w-4') ?></a>
        </div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
            <?= View::partial('components/stat', ['label' => 'Events recorded', 'value' => $auditToday, 'icon' => 'shield-check', 'tone' => 'bg-midnight-tint text-midnight', 'sub' => 'Tamper-evident activity log']) ?>
            <?= View::partial('components/stat', ['label' => 'Failed sign-ins', 'value' => $failedLoginsToday, 'icon' => 'lock', 'tone' => (int)$failedLoginsToday > 0 ? 'bg-danger-tint text-danger-fg ring-1 ring-danger-fg/30' : 'bg-smoke text-muted', 'sub' => 'Authentication failures']) ?>
            <?= View::partial('components/stat', ['label' => 'Access refused', 'value' => $deniedToday, 'icon' => 'shield', 'tone' => (int)$deniedToday > 0 ? 'bg-warning-tint text-warning-text' : 'bg-smoke text-muted', 'sub' => 'Permission violations']) ?>
        </div>
        <?php if ($latestAudit): ?>
            <ul class="card mt-3 divide-y divide-line overflow-hidden" role="list" aria-label="Latest events">
                <?php foreach ($latestAudit as $a): ?>
                    <li class="flex flex-col gap-1 p-3.5 text-sm sm:flex-row sm:items-center sm:gap-3 sm:px-6 hover:bg-smoke-2/50 transition-colors">
                        <code class="shrink-0 text-xs font-mono font-semibold bg-smoke-2 border border-line px-1.5 py-0.5 rounded text-ink"><?= e($a['action']) ?></code>
                        <span class="min-w-0 flex-1 truncate font-medium text-ink"><?= e($a['description']) ?></span>
                        <span class="shrink-0 text-xs text-muted"><?= e($a['user_email'] ?? 'system') ?> · <?= e(fmt_relative($a['created_at'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endif; ?>

</div>

