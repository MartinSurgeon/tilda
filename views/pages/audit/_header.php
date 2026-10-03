<?php
/** @var string $tab @var ?array $filters @var ?int $total */
$exportType = $tab === 'changes' ? 'changes' : 'activity';
$query = array_filter($filters ?? []);
?>
<header class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="page-title">Audit log</h1>
        <p class="page-subtitle">A permanent, tamper-evident record. Entries can be read and exported, never changed or deleted.</p>
    </div>
    <?php if ($tab !== 'integrity' && can('audit.export')): ?>
        <div class="flex gap-2">
            <a href="<?= e(url('/audit/export', ['type' => $exportType, 'format' => 'csv'] + $query)) ?>" class="btn-secondary flex-1 sm:flex-none"><?= icon('download') ?> CSV</a>
            <a href="<?= e(url('/audit/export', ['type' => $exportType, 'format' => 'pdf'] + $query)) ?>" class="btn-secondary flex-1 sm:flex-none"><?= icon('file') ?> PDF</a>
        </div>
    <?php endif; ?>
</header>

<nav class="-mx-4 mb-5 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Audit sections">
    <ul class="flex min-w-max gap-2" role="list">
        <?php
        $tabs = ['activity' => ['Activity', '/audit'], 'changes' => ['Data changes', '/audit/changes']];
        if (can('audit.verify')) {
            $tabs['integrity'] = ['Integrity check', '/audit/integrity'];
        }
        foreach ($tabs as $key => [$label, $path]): $current = $tab === $key; ?>
            <li>
                <a href="<?= e(url($path)) ?>" <?= $current ? 'aria-current="page"' : '' ?>
                   class="inline-flex min-h-touch items-center rounded-full border px-4 text-sm font-semibold no-underline hover:no-underline <?= $current ? 'border-teal bg-teal text-white' : 'border-line bg-white text-charcoal hover:bg-smoke' ?>"><?= e($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
