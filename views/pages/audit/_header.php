<?php
/** @var string $tab @var ?array $filters @var ?int $total */
$exportType = $tab === 'changes' ? 'changes' : 'activity';
$query = array_filter($filters ?? []);
?>
<header class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="page-title">Activity log</h1>
        <p class="page-subtitle">A permanent record of who did what. Entries can be read and exported, but never changed or deleted.</p>
    </div>
    <?php if ($tab !== 'integrity' && can('audit.export')): ?>
        <div class="flex gap-2">
            <a href="<?= e(url('/audit/export', ['type' => $exportType, 'format' => 'csv'] + $query)) ?>" class="btn-secondary flex-1 sm:flex-none"><?= icon('download') ?> CSV</a>
            <a href="<?= e(url('/audit/export', ['type' => $exportType, 'format' => 'pdf'] + $query)) ?>" class="btn-secondary flex-1 sm:flex-none"><?= icon('file') ?> PDF</a>
        </div>
    <?php endif; ?>
</header>

<nav class="-mx-4 mb-5 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Audit sections">
    <ul class="tab-strip min-w-max" role="list">
        <?php
        $tabs = ['activity' => ['Who did what', '/audit'], 'changes' => ['Record changes', '/audit/changes']];
        if (can('audit.verify')) {
            $tabs['integrity'] = ['Safety check', '/audit/integrity'];
        }
        foreach ($tabs as $key => [$label, $path]): $current = $tab === $key; ?>
            <li>
                <a href="<?= e(url($path)) ?>" <?= $current ? 'aria-current="page"' : '' ?>
                   class="tab"><?= e($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
