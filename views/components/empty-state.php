<?php
/**
 * @var string $icon @var string $title @var string $text
 * @var ?array $action [label, href]
 * @var ?int $level heading level that fits the surrounding outline (default 2)
 */
$tag = 'h' . max(2, min(4, (int) ($level ?? 2)));
?>
<div class="flex flex-col items-center px-4 py-8 text-center">
    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-smoke text-muted"><?= icon($icon, 'h-7 w-7') ?></span>
    <<?= $tag ?> class="mt-4 text-base font-semibold"><?= e($title) ?></<?= $tag ?>>
    <p class="mt-1 max-w-sm text-sm text-muted"><?= e($text) ?></p>
    <?php if (!empty($action)): ?>
        <a href="<?= e($action[1]) ?>" class="btn-primary mt-5"><?= e($action[0]) ?></a>
    <?php endif; ?>
</div>
