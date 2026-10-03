<?php
/** @var string $label @var int|string $value @var string $icon @var string $tone @var ?string $href @var ?string $sub */
$tag = empty($href) ? 'div' : 'a';
?>
<<?= $tag ?> <?= $tag === 'a' ? 'href="' . e($href) . '"' : '' ?> class="stat <?= $tag === 'a' ? 'text-charcoal no-underline hover:border-line-strong hover:no-underline' : '' ?>">
    <span class="stat-icon <?= e($tone) ?>"><?= icon($icon) ?></span>
    <div class="min-w-0">
        <p class="stat-value"><?= e((string) $value) ?></p>
        <p class="stat-label"><?= e($label) ?><?= !empty($sub) ? ' · ' . e($sub) : '' ?></p>
    </div>
</<?= $tag ?>>
