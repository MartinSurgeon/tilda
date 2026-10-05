<?php
/** @var string $view @var array $counts @var array $tickets @var int $page @var int $pages */
$awaiting = (int) ($counts['awaiting'] ?? 0);
?>
<header class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="page-title">My tickets</h1>
        <p class="page-subtitle">Problems you have reported.</p>
    </div>
    <?php if (can('ticket.create')): ?>
        <a href="<?= e(url('/tickets/create')) ?>" class="btn-primary"><?= icon('plus') ?> Report a problem</a>
    <?php endif; ?>
</header>

<div id="live-mine" data-live>
<?php if ($awaiting > 0): ?>
    <div class="alert-info mb-5" role="status">
        <?= icon('check-circle', 'mt-0.5 h-5 w-5 shrink-0') ?>
        <p><strong><?= $awaiting ?> <?= $awaiting === 1 ? 'ticket needs' : 'tickets need' ?> your confirmation.</strong> IT says <?= $awaiting === 1 ? 'it is' : 'they are' ?> fixed. Open <?= $awaiting === 1 ? 'it' : 'each one' ?> to confirm or reopen.</p>
    </div>
<?php endif; ?>

<nav class="mb-4" aria-label="Ticket views">
    <ul class="flex gap-2" role="list">
        <?php foreach (['active' => 'Open', 'done' => 'Resolved & closed'] as $key => $label): $current = $view === $key; ?>
            <li>
                <a href="<?= e(url('/tickets/mine', ['view' => $key === 'active' ? null : $key])) ?>" <?= $current ? 'aria-current="page"' : '' ?>
                   class="inline-flex min-h-touch items-center gap-2 rounded-full border px-4 text-sm font-semibold no-underline hover:no-underline <?= $current ? 'border-teal bg-teal text-white' : 'border-line bg-surface text-charcoal hover:bg-smoke' ?>">
                    <?= e($label) ?>
                    <span class="rounded-full px-2 text-xs <?= $current ? 'bg-teal-dark text-white' : 'bg-smoke text-ink' ?>"><?= (int) ($counts[$key] ?? 0) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<?php if (!$tickets): ?>
    <div class="card">
        <?= App\Core\View::partial('components/empty-state', $view === 'active'
            ? ['icon' => 'ticket', 'title' => 'No open tickets', 'text' => 'If something is not working, report it and you can follow it here.',
               'action' => can('ticket.create') ? ['Report a problem', url('/tickets/create')] : null]
            : ['icon' => 'archive', 'title' => 'Nothing here yet', 'text' => 'Tickets move here once they are resolved or closed.']) ?>
    </div>
<?php else: ?>
    <ul class="grid gap-3 md:grid-cols-2" role="list">
        <?php foreach ($tickets as $t): ?>
            <li><?= App\Core\View::partial('pages/tickets/_card', ['t' => $t, 'staff' => false]) ?></li>
        <?php endforeach; ?>
    </ul>
    <?= App\Core\View::partial('components/pagination', ['page' => $page, 'pages' => $pages, 'path' => '/tickets/mine',
        'query' => ['view' => $view === 'active' ? null : $view]]) ?>
<?php endif; ?>
</div>
