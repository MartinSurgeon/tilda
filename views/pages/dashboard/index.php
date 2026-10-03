<?php
use App\Services\TicketMeta;

/** @var array $sections */
$me = user();
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', $me['full_name'])[0];
$statusTotals = array_column($byStatus ?? [], 'total', 'status');
?>
<header class="mb-6">
    <h1 class="page-title"><?= e($greeting . ', ' . $firstName) ?></h1>
    <p class="page-subtitle"><?= e($me['role_name']) ?><?= $me['department_name'] ? ' · ' . e($me['department_name']) : '' ?></p>
</header>

<div class="space-y-8">

<?php if (can('ticket.create') && !can('ticket.view_all')): ?>
    <!-- Primary action first (serial position): staff come here to report a problem. -->
    <section class="card overflow-hidden" aria-labelledby="report-title">
        <div class="flex flex-col gap-4 bg-brand-linear-1 p-6 text-white sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="report-title" class="text-lg font-bold text-white">Something not working?</h2>
                <p class="mt-1 text-sm text-white">Tell IT in under a minute. We will keep you updated here.</p>
            </div>
            <a href="<?= e(url('/tickets/create')) ?>" class="btn bg-white text-teal-darker hover:bg-teal-tint">
                <?= icon('plus') ?> Report a problem
            </a>
        </div>
    </section>
<?php endif; ?>

<?php if (in_array('queue', $sections, true)): ?>
    <section aria-labelledby="queue-title">
        <h2 id="queue-title" class="section-title mb-3">My work</h2>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <?php foreach ([
                ['Unassigned', $queue['unassigned'] ?? 0, 'inbox', 'bg-info-tint text-info-text'],
                ['Assigned to me', $queue['mine_active'] ?? 0, 'user-check', 'bg-teal-tint text-teal-darker'],
                ['My on hold', $queue['mine_on_hold'] ?? 0, 'pause-circle', 'bg-warning-tint text-warning-text'],
                ['Overdue (SLA)', $queue['overdue'] ?? 0, 'alert-triangle', 'bg-danger-tint text-danger'],
            ] as [$label, $value, $ico, $tone]): ?>
                <div class="stat">
                    <span class="stat-icon <?= $tone ?>"><?= icon($ico) ?></span>
                    <div><p class="stat-value"><?= (int) $value ?></p><p class="stat-label"><?= e($label) ?></p></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (in_array('overview', $sections, true)): ?>
    <section aria-labelledby="overview-title">
        <h2 id="overview-title" class="section-title mb-3">All tickets by status</h2>
        <div class="card card-body">
            <?php if (array_sum($statusTotals) === 0): ?>
                <?= App\Core\View::partial('components/empty-state', [
                    'icon' => 'chart', 'title' => 'No tickets yet', 'level' => 3,
                    'text' => 'Counts and trends will appear here as soon as staff start reporting problems.',
                ]) ?>
            <?php else: ?>
                <ul class="flex flex-wrap gap-3" role="list">
                    <?php foreach (TicketMeta::STATUSES as $key => $meta): ?>
                        <li class="flex items-center gap-2"><?= TicketMeta::statusBadge($key) ?> <span class="font-semibold text-ink"><?= (int) ($statusTotals[$key] ?? 0) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (in_array('mine', $sections, true)): ?>
    <section aria-labelledby="mine-title">
        <div class="mb-3 flex items-center justify-between">
            <h2 id="mine-title" class="section-title">Tickets I raised</h2>
            <?php if ($myTickets): ?><a href="<?= e(url('/tickets/mine')) ?>" class="text-sm font-semibold">View all</a><?php endif; ?>
        </div>
        <div class="card">
            <?php if (!$myTickets): ?>
                <div class="card-body">
                    <?= App\Core\View::partial('components/empty-state', [
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
                                    <p class="text-sm text-muted"><?= e($t['ref']) ?> · updated <?= e(fmt_relative($t['updated_at'])) ?></p>
                                </div>
                                <?= TicketMeta::statusBadge($t['status']) ?>
                                <?= icon('chevron-right', 'h-5 w-5 text-muted') ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (in_array('audit', $sections, true)): ?>
    <section aria-labelledby="audit-title">
        <h2 id="audit-title" class="section-title mb-3">Audit trail today</h2>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="stat">
                <span class="stat-icon bg-midnight-tint text-midnight"><?= icon('shield-check') ?></span>
                <div><p class="stat-value"><?= (int) $auditToday ?></p><p class="stat-label">Events recorded</p></div>
            </div>
            <div class="stat">
                <span class="stat-icon bg-danger-tint text-danger"><?= icon('lock') ?></span>
                <div><p class="stat-value"><?= (int) $failedLoginsToday ?></p><p class="stat-label">Failed sign-ins</p></div>
            </div>
        </div>
    </section>
<?php endif; ?>

</div>
