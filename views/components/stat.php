<?php
/** @var string $label @var int|string $value @var string $icon @var string $tone @var ?string $href @var ?string $sub */
$tag = empty($href) ? 'div' : 'a';
?>
<<?= $tag ?> <?= $tag === 'a' ? 'href="' . e($href) . '"' : '' ?> class="group relative flex flex-col justify-between rounded-xl border border-line bg-surface p-4 sm:p-5 shadow-card transition-all duration-150 <?= $tag === 'a' ? 'hover:border-line-strong hover:shadow-raised hover:-translate-y-0.5 no-underline hover:no-underline' : '' ?>">
    <div class="flex items-center justify-between">
        <span class="flex h-10 w-10 sm:h-11 sm:w-11 shrink-0 items-center justify-center rounded-lg <?= e($tone) ?>">
            <?= icon($icon, 'h-5 w-5 sm:h-6 sm:w-6') ?>
        </span>
        <?php if ($tag === 'a'): ?>
            <span class="text-muted transition-transform duration-150 group-hover:translate-x-0.5 group-hover:text-ink" aria-hidden="true">
                <?= icon('chevron-right', 'h-4 w-4') ?>
            </span>
        <?php endif; ?>
    </div>
    <div class="mt-3">
        <p class="text-2xl sm:text-3xl font-extrabold tracking-tight text-ink tabular-nums"><?= e((string) $value) ?></p>
        <p class="mt-1 text-sm font-semibold text-ink/80 leading-snug"><?= e($label) ?></p>
        <?php if (!empty($sub)): ?>
            <p class="mt-0.5 text-xs text-muted"><?= e($sub) ?></p>
        <?php endif; ?>
    </div>
</<?= $tag ?>>

