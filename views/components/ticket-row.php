<?php
/**
 * One ticket in a list. Same row on the dashboard, the queue and My tickets.
 * Shows the title first, one quiet line of context, and only the signals that
 * need attention (a Critical/High priority, an overdue or close deadline).
 *
 * @var array   $t
 * @var string  $mode  'staff' (IT view) or 'mine' (the person who raised it)
 * @var ?string $take  queue URL to return to: adds a "Take" button for unowned tickets
 */
use App\Services\TicketMeta;

$mode ??= 'staff';
$take ??= null;
$staff = $mode === 'staff';
$me = user();
// Callers select different columns (the dashboard's "raised by me" list has no assignee_id).
$assigneeId = $t['assignee_id'] ?? null;
$mineAssigned = $assigneeId !== null && (int) $assigneeId === (int) $me['id'];
$assignee = $staff && $mineAssigned ? 'You' : ($t['assignee_name'] ?? null);
$canTake = $staff && $take && $assigneeId === null && in_array($t['status'], ['open', 'reopened'], true) && can('ticket.work');
$signals = $staff ? TicketMeta::priorityAlert($t) . TicketMeta::deadlineAlert($t) : TicketMeta::statusBadge($t['status']);
?>
<div class="relative flex flex-col gap-3 px-4 py-4 transition-colors hover:bg-smoke-2/70 sm:flex-row sm:items-center sm:gap-4 sm:px-6">
    <a href="<?= e(url('/tickets/' . $t['id'])) ?>" class="min-w-0 flex-1 text-charcoal no-underline after:absolute after:inset-0 after:content-[''] hover:no-underline">
        <span class="block line-clamp-2 font-semibold text-ink sm:line-clamp-1"><?= e($t['title']) ?></span>
        <span class="mt-1 block text-sm text-muted">
            <span class="tabular-nums"><?= e($t['ref']) ?></span>
            <?php if ($staff): ?>
                · <?= e($t['department_name']) ?>
                · <?php if ($assignee): ?><?= e($assignee) ?><?php else: ?><span class="font-medium text-warning-text">Unassigned</span><?php endif; ?>
            <?php else: ?>
                · <?= e($assignee ? $assignee . ' is on it' : 'Waiting for IT') ?>
                · <?= e(fmt_relative($t['updated_at'])) ?>
            <?php endif; ?>
        </span>
    </a>
    <?php if ($signals !== '' || $canTake): ?>
        <div class="flex flex-wrap items-center gap-2 sm:shrink-0 sm:justify-end">
            <?= $signals ?>
            <?php if ($canTake): ?>
                <form method="post" action="<?= e(url('/tickets/' . $t['id'] . '/accept')) ?>" class="relative z-10">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return" value="<?= e($take) ?>">
                    <button type="submit" class="btn-secondary" aria-label="Take <?= e($t['ref']) ?>" data-loading-text="Taking…" data-kb-take><?= icon('user-check', 'h-4 w-4') ?> Take</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
