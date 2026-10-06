<?php
/**
 * @var array $t @var bool $staff  true = IT view (shows requester + SLA)
 * @var ?string $take  queue URL to return to: shows a "Take" button for unowned tickets
 */
use App\Services\TicketMeta;

$canTake = !empty($take) && $t['assignee_id'] === null && in_array($t['status'], ['open', 'reopened'], true) && can('ticket.work');
?>
<div class="card overflow-hidden hover:border-line-strong">
    <a href="<?= e(url('/tickets/' . $t['id'])) ?>" class="block p-4 text-charcoal no-underline hover:no-underline">
        <div class="flex flex-wrap items-center gap-2">
            <?= TicketMeta::priorityBadge($t['priority_name'], $t['tone'], $t['priority_icon']) ?>
            <?= TicketMeta::statusBadge($t['status']) ?>
            <?php if ($staff): ?><?= TicketMeta::slaBadge($t) ?><?php endif; ?>
        </div>
        <p class="mt-2 font-semibold text-ink"><?= e($t['title']) ?></p>
        <p class="mt-1 text-sm text-muted">
            <span class="ticket-ref mr-1"><?= e($t['ref']) ?></span>
            · <?= e($staff ? $t['requester_name'] . ', ' . $t['department_name'] : $t['category_name']) ?>
        </p>
        <p class="mt-1 flex items-center gap-1 text-sm text-muted">
            <?= icon('user', 'h-4 w-4') ?>
            <?= e($t['assignee_name'] ?? 'Not yet assigned') ?>
            <span aria-hidden="true">·</span> updated <?= e(fmt_relative($t['updated_at'])) ?>
        </p>
    </a>
    <?php if ($canTake): ?>
        <form method="post" action="<?= e(url('/tickets/' . $t['id'] . '/accept')) ?>" class="border-t border-line bg-smoke-2 px-4 py-2">
            <?= csrf_field() ?>
            <input type="hidden" name="return" value="<?= e($take) ?>">
            <button type="submit" class="btn-primary w-full" data-loading-text="Taking…" data-kb-take><?= icon('user-check') ?> Take <?= e($t['ref']) ?></button>
        </form>
    <?php endif; ?>
</div>
