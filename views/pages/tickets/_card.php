<?php
/** @var array $t @var bool $staff  true = IT view (shows requester + SLA) */
use App\Services\TicketMeta;
?>
<a href="<?= e(url('/tickets/' . $t['id'])) ?>" class="card block p-4 text-charcoal no-underline hover:border-line-strong hover:no-underline">
    <div class="flex flex-wrap items-center gap-2">
        <?= TicketMeta::priorityBadge($t['priority_name'], $t['tone'], $t['priority_icon']) ?>
        <?= TicketMeta::statusBadge($t['status']) ?>
        <?php if ($staff): ?><?= TicketMeta::slaBadge($t) ?><?php endif; ?>
    </div>
    <p class="mt-2 font-semibold text-ink"><?= e($t['title']) ?></p>
    <p class="mt-1 text-sm text-muted">
        <span class="font-mono"><?= e($t['ref']) ?></span>
        · <?= e($staff ? $t['requester_name'] . ', ' . $t['department_name'] : $t['category_name']) ?>
    </p>
    <p class="mt-1 flex items-center gap-1 text-sm text-muted">
        <?= icon('user', 'h-4 w-4') ?>
        <?= e($t['assignee_name'] ?? 'Not yet assigned') ?>
        <span aria-hidden="true">·</span> updated <?= e(fmt_relative($t['updated_at'])) ?>
    </p>
</a>
