<?php
/** @var int $page @var int $pages @var string $path @var array $query */
if ($pages <= 1) {
    return;
}
$link = static fn (int $p) => url($path, $query + ['page' => $p > 1 ? $p : null]);
?>
<nav class="mt-6 flex items-center justify-between gap-3" aria-label="Pagination">
    <?php if ($page > 1): ?>
        <a href="<?= e($link($page - 1)) ?>" class="btn-secondary"><?= icon('chevron-left', 'h-4 w-4') ?> Previous</a>
    <?php else: ?>
        <span></span>
    <?php endif; ?>
    <p class="text-sm text-muted">Page <?= (int) $page ?> of <?= (int) $pages ?></p>
    <?php if ($page < $pages): ?>
        <a href="<?= e($link($page + 1)) ?>" class="btn-secondary">Next <?= icon('chevron-right', 'h-4 w-4') ?></a>
    <?php else: ?>
        <span></span>
    <?php endif; ?>
</nav>
