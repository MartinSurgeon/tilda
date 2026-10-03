<?php
/** @var array $files @var string $class */
use App\Services\Uploads;
?>
<ul class="<?= e($class ?? '') ?> flex flex-wrap gap-2" role="list" aria-label="Attachments">
    <?php foreach ($files as $f): $isImage = str_starts_with($f['mime_type'], 'image/'); ?>
        <li>
            <a href="<?= e(url('/attachments/' . $f['id'])) ?>" <?= $isImage ? 'target="_blank" rel="noopener"' : '' ?>
               class="inline-flex min-h-touch max-w-full items-center gap-2 rounded-lg border border-line bg-smoke-2 px-3 py-2 text-sm text-ink no-underline hover:border-line-strong hover:no-underline">
                <?= icon($isImage ? 'eye' : 'file', 'h-4 w-4 shrink-0 text-muted') ?>
                <span class="truncate"><?= e($f['original_name']) ?></span>
                <span class="shrink-0 text-muted"><?= e(Uploads::humanSize((int) $f['size_bytes'])) ?></span>
                <?php if ((int) $f['is_internal'] === 1): ?><span class="sr-only">(internal)</span><?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
