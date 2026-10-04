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
<header class="mb-6 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="page-title"><?= e($greeting . ', ' . $firstName) ?></h1>
        <p class="page-subtitle"><?= e($me['role_name']) ?><?= $me['department_name'] ? ' · ' . e($me['department_name']) : '' ?></p>
    </div>
    <p class="text-sm text-muted" data-live-stamp aria-live="polite">Updates automatically</p>
</header>

<div class="space-y-8">

<?php if (can('ticket.create') && !can('ticket.view_all') && !$has('overview')): ?>
    <section class="card overflow-hidden" aria-labelledby="report-title">
        <div class="flex flex-col gap-4 bg-brand-linear-1 p-6 text-white sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="report-title" class="text-lg font-bold text-white">Something not working?</h2>
                <p class="mt-1 text-sm text-white">Tell IT in under a minute. We will keep you updated here.</p>
            </div>
            <a href="<?= e(url('/tickets/create')) ?>" class="btn bg-white text-teal-darker hover:bg-teal-tint"><?= icon('plus') ?> Report a problem</a>
        </div>
    </section>
<?php endif; ?>

<?php if ($has('mine')): ?>
    <!-- Most important for staff: tickets waiting for their confirmation. Wrapper always present so a live refresh can fill it. -->
    <div id="live-awaiting" data-live <?= $awaiting ? "" : "hidden" ?>><?php if ($awaiting): ?>
    <section class="alert-info" aria-labelledby="awaiting-title">
        <?= icon('check-circle', 'mt-0.5 h-5 w-5 shrink-0') ?>
        <div class="flex-1">
            <h2 id="awaiting-title" class="font-semibold text-info-text">IT says <?= count($awaiting) === 1 ? 'this is' : 'these are' ?> fixed. Please confirm.</h2>
            <ul class="mt-2 space-y-1" role="list">
                <?php foreach ($awaiting as $a): ?>
                    <li><a href="<?= e(url('/tickets/' . $a['id'])) ?>" class="inline-flex min-h-touch items-center font-semibold text-midnight"><?= e($a['ref']) ?></a> · <?= e($a['title']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?></div>
<?php endif; ?>

<?php if ($has('overview')): $k = $kpi; ?>
    <section id="live-kpi" data-live aria-labelledby="kpi-title">
        <h2 id="kpi-title" class="section-title mb-3">Service at a glance</h2>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <?= View::partial('components/stat', ['label' => 'Open tickets', 'value' => $k['open_now'], 'icon' => 'inbox', 'tone' => 'bg-info-tint text-info-text', 'href' => can('ticket.view_all') ? url('/tickets', ['view' => 'active']) : null]) ?>
            <?= View::partial('components/stat', ['label' => 'Waiting for a technician', 'value' => $k['unassigned'], 'icon' => 'user-check', 'tone' => 'bg-midnight-tint text-midnight', 'href' => can('ticket.view_all') ? url('/tickets', ['view' => 'unassigned']) : null]) ?>
            <?= View::partial('components/stat', ['label' => 'Overdue (SLA)', 'value' => $k['overdue'], 'icon' => 'alert-triangle', 'tone' => 'bg-danger-tint text-danger', 'href' => can('ticket.view_all') ? url('/tickets', ['view' => 'overdue']) : null]) ?>
            <?= View::partial('components/stat', [
                'label' => 'Resolved on time this month', 'icon' => 'check-circle', 'tone' => 'bg-green-tint text-green-text',
                'value' => $k['sla_month'] === null ? '—' : $k['sla_month'] . '%',
                'sub'   => $k['resolved_month'] . ' resolved',
            ]) ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($has('queue')): ?>
    <section id="live-queue" data-live aria-labelledby="queue-title">
        <div class="mb-3 flex items-center justify-between">
            <h2 id="queue-title" class="section-title">Needs attention</h2>
            <a href="<?= e(url('/tickets')) ?>" class="inline-flex min-h-touch items-center text-sm font-semibold">Open queue</a>
        </div>
        <?php if (!$has('overview')): $q = $queue; ?>
            <div class="mb-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <?= View::partial('components/stat', ['label' => 'Assigned to me', 'value' => (int) ($q['mine'] ?? 0), 'icon' => 'user-check', 'tone' => 'bg-teal-tint text-teal-darker', 'href' => url('/tickets', ['view' => 'mine'])]) ?>
                <?= View::partial('components/stat', ['label' => 'Unassigned', 'value' => (int) ($q['unassigned'] ?? 0), 'icon' => 'inbox', 'tone' => 'bg-info-tint text-info-text', 'href' => url('/tickets', ['view' => 'unassigned'])]) ?>
                <?= View::partial('components/stat', ['label' => 'All open', 'value' => (int) ($q['active'] ?? 0), 'icon' => 'ticket', 'tone' => 'bg-midnight-tint text-midnight', 'href' => url('/tickets', ['view' => 'active'])]) ?>
                <?= View::partial('components/stat', ['label' => 'Overdue (SLA)', 'value' => (int) ($q['overdue'] ?? 0), 'icon' => 'alert-triangle', 'tone' => 'bg-danger-tint text-danger', 'href' => url('/tickets', ['view' => 'overdue'])]) ?>
            </div>
        <?php endif; ?>
        <div class="card">
            <?php if (!$attention): ?>
                <?= View::partial('components/empty-state', ['icon' => 'check-circle', 'title' => 'All clear', 'level' => 3,
                    'text' => 'Nothing is waiting for you or unassigned right now.']) ?>
            <?php else: ?>
                <ul class="divide-y divide-line" role="list">
                    <?php foreach ($attention as $t): ?>
                        <li>
                            <a href="<?= e(url('/tickets/' . $t['id'])) ?>" class="flex min-h-touch flex-col gap-2 px-4 py-3 text-charcoal no-underline hover:bg-smoke-2 hover:no-underline sm:flex-row sm:items-center sm:px-6">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold text-ink"><?= e($t['title']) ?></p>
                                    <p class="text-sm text-muted"><span class="font-mono"><?= e($t['ref']) ?></span> · <?= e($t['department_name']) ?> · <?= $t['assignee_id'] ? 'Yours' : 'Unassigned' ?></p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <?= TicketMeta::priorityBadge($t['priority_name'], $t['tone'], $t['priority_icon']) ?>
                                    <?= TicketMeta::slaBadge($t) ?>
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
            <div class="card-header"><h2 id="trend-title" class="card-title">Last 14 days</h2></div>
            <div class="card-body">
                <?= View::partial('components/trend-chart', ['trend' => $trend]) ?>
            </div>
        </section>
        <section class="card" aria-labelledby="prio-title">
            <div class="card-header"><h2 id="prio-title" class="card-title">Open by priority</h2></div>
            <?php $maxP = max(1, ...array_map(static fn ($p) => (int) $p['total'], $byPriority)); ?>
            <ul class="card-body space-y-4" role="list">
                <?php foreach ($byPriority as $p): $n = (int) $p['total']; ?>
                    <li>
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <?= TicketMeta::priorityBadge($p['name'], $p['tone'], $p['icon']) ?>
                            <span class="font-semibold text-ink"><?= $n ?></span>
                        </div>
                        <meter class="meter meter-<?= e($p['tone']) ?>" min="0" max="<?= $maxP ?>" value="<?= $n ?>" aria-label="<?= e($p['name']) ?>: <?= $n ?> open"><?= $n ?></meter>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card lg:col-span-3" aria-labelledby="workload-title">
            <div class="card-header"><h2 id="workload-title" class="card-title">Technician workload</h2></div>
            <?php if (!$workload): ?>
                <p class="card-body text-sm text-muted">No technicians yet.</p>
            <?php else: ?>
                <div class="hidden overflow-x-auto md:block">
                    <table class="table">
                        <thead><tr>
                            <th scope="col">Technician</th><th scope="col" class="text-right">Not started</th><th scope="col" class="text-right">In progress</th>
                            <th scope="col" class="text-right">On hold</th><th scope="col" class="text-right">Overdue</th><th scope="col" class="text-right">Resolved this month</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($workload as $w): ?>
                            <tr>
                                <th scope="row" class="font-semibold text-ink"><?= e($w['full_name']) ?></th>
                                <td class="text-right"><?= (int) $w['waiting'] ?></td>
                                <td class="text-right"><?= (int) $w['in_progress'] ?></td>
                                <td class="text-right"><?= (int) $w['on_hold'] ?></td>
                                <td class="text-right"><?= (int) $w['overdue'] ? '<span class="badge-danger">' . icon('alert-triangle', 'h-3.5 w-3.5') . (int) $w['overdue'] . '</span>' : '0' ?></td>
                                <td class="text-right"><?= (int) $w['resolved_month'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <ul class="divide-y divide-line md:hidden" role="list">
                    <?php foreach ($workload as $w): ?>
                        <li class="px-4 py-3">
                            <p class="font-semibold text-ink"><?= e($w['full_name']) ?></p>
                            <p class="text-sm text-muted">
                                <?= (int) $w['waiting'] + (int) $w['in_progress'] + (int) $w['on_hold'] ?> open
                                (<?= (int) $w['in_progress'] ?> in progress, <?= (int) $w['on_hold'] ?> on hold)
                                · <?= (int) $w['resolved_month'] ?> resolved this month
                                <?php if ((int) $w['overdue']): ?> · <span class="font-semibold text-danger"><?= (int) $w['overdue'] ?> overdue</span><?php endif; ?>
                            </p>
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
            <?php if ($myTickets): ?><a href="<?= e(url('/tickets/mine')) ?>" class="inline-flex min-h-touch items-center text-sm font-semibold">View all</a><?php endif; ?>
        </div>
        <div class="card">
            <?php if (!$myTickets): ?>
                <div class="card-body">
                    <?= View::partial('components/empty-state', [
                        'icon' => 'ticket', 'title' => 'You have not reported any problems', 'level' => 3,
                        'text' => 'When you do, you can follow their progress here.',
                    ]) ?>
                </div>
            <?php else: ?>
                <ul class="divide-y divide-line" role="list">
                    <?php foreach ($myTickets as $t): ?>
                        <li>
                            <a href="<?= e(url('/tickets/' . $t['id'])) ?>" class="flex min-h-touch items-center gap-3 px-4 py-3 text-charcoal no-underline hover:bg-smoke-2 hover:no-underline sm:px-6">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold text-ink"><?= e($t['title']) ?></p>
                                    <p class="text-sm text-muted"><?= e($t['ref']) ?> · <?= e($t['assignee_name'] ? $t['assignee_name'] . ' is on it' : 'Waiting for IT') ?> · <?= e(fmt_relative($t['updated_at'])) ?></p>
                                </div>
                                <?= TicketMeta::statusBadge($t['status']) ?>
                                <?= icon('chevron-right', 'h-5 w-5 shrink-0 text-muted') ?>
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
            <a href="<?= e(url('/audit')) ?>" class="inline-flex min-h-touch items-center text-sm font-semibold">Open audit log</a>
        </div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
            <?= View::partial('components/stat', ['label' => 'Events recorded', 'value' => $auditToday, 'icon' => 'shield-check', 'tone' => 'bg-midnight-tint text-midnight']) ?>
            <?= View::partial('components/stat', ['label' => 'Failed sign-ins', 'value' => $failedLoginsToday, 'icon' => 'lock', 'tone' => 'bg-danger-tint text-danger']) ?>
            <?= View::partial('components/stat', ['label' => 'Access refused', 'value' => $deniedToday, 'icon' => 'shield', 'tone' => 'bg-warning-tint text-warning-text']) ?>
        </div>
        <?php if ($latestAudit): ?>
            <ul class="card mt-3 divide-y divide-line" role="list" aria-label="Latest events">
                <?php foreach ($latestAudit as $a): ?>
                    <li class="flex flex-col gap-0.5 px-4 py-3 text-sm sm:flex-row sm:items-center sm:gap-3 sm:px-6">
                        <code class="shrink-0 text-xs text-muted"><?= e($a['action']) ?></code>
                        <span class="min-w-0 flex-1 truncate text-ink"><?= e($a['description']) ?></span>
                        <span class="shrink-0 text-muted"><?= e($a['user_email'] ?? 'system') ?> · <?= e(fmt_relative($a['created_at'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endif; ?>

</div>
