<?php
/** @var array $t @var array $transitions @var bool $isRequester */
$rows = [
    ['Requested by', $t['requester_name'] . ($t['requester_phone'] ? ' · ' . $t['requester_phone'] : ''), 'user'],
    ['Department', $t['department_name'], 'building'],
    ['Location', $t['location'] !== '' ? $t['location'] : '—', 'map-pin'],
    ['Category', $t['category_name'] . ($t['subcategory_name'] ? ' › ' . $t['subcategory_name'] : ''), $t['category_icon']],
    ['Assigned to', $t['assignee_name'] ?? 'Not yet assigned', 'user-check'],
    ['Reported', fmt_date($t['created_at']), 'clock'],
];
$cancel = $isRequester && isset($transitions['closed']) && $transitions['closed']['as'] === 'requester' && $t['status'] !== 'resolved';
?>
<section class="card" aria-labelledby="details-title">
    <div class="card-header"><h2 id="details-title" class="card-title">Details</h2></div>
    <dl class="divide-y divide-line">
        <?php foreach ($rows as [$label, $value, $ico]): ?>
            <div class="px-4 py-3 sm:px-6">
                <dt class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted"><?= icon($ico, 'h-4 w-4 shrink-0') ?><?= e($label) ?></dt>
                <dd class="mt-0.5 break-words pl-6 text-sm text-ink"><?= e($value) ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
</section>

<?php if (can('ticket.view_all')): ?>
    <section class="card" aria-labelledby="sla-title">
        <div class="card-header"><h2 id="sla-title" class="card-title">Service targets</h2></div>
        <dl class="space-y-3 px-4 py-4 text-sm sm:px-6">
            <div>
                <dt class="text-muted">First response</dt>
                <dd class="text-ink">
                    <?php if ($t['first_response_at']): $met = strtotime($t['first_response_at']) <= strtotime($t['response_due_at']); ?>
                        <?= e(fmt_date($t['first_response_at'])) ?>
                        <span class="<?= $met ? 'badge-outline-success' : 'badge-outline-danger' ?> ml-1"><?= icon($met ? 'check' : 'alert-triangle', 'h-3.5 w-3.5') ?><?= $met ? 'On time' : 'Late' ?></span>
                    <?php else: ?>
                        Due <?= e(fmt_date($t['response_due_at'])) ?>
                        <?php if (strtotime($t['response_due_at']) < time()): ?><span class="badge-outline-danger ml-1"><?= icon('alert-triangle', 'h-3.5 w-3.5') ?>Overdue</span><?php endif; ?>
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt class="text-muted">Resolution</dt>
                <dd class="text-ink"><?= $t['resolved_at'] ? 'Resolved ' . e(fmt_date($t['resolved_at'])) : 'Due ' . e(fmt_date($t['resolve_due_at'])) ?></dd>
            </div>
            <?php if ((int) $t['hold_minutes'] > 0 || $t['on_hold_since']): ?>
                <div>
                    <dt class="text-muted">Time on hold (not counted)</dt>
                    <dd class="text-ink"><?= e(App\Services\Sla::duration(((int) $t['hold_minutes']) * 60 + ($t['on_hold_since'] ? time() - strtotime($t['on_hold_since']) : 0))) ?></dd>
                </div>
            <?php endif; ?>
        </dl>
    </section>
<?php endif; ?>

<?php if ($cancel): ?>
    <form method="post" action="<?= e(url('/tickets/' . $t['id'] . '/status')) ?>" class="text-center"
          data-confirm="Cancel this request? IT will stop working on it." data-confirm-label="Cancel request">
        <?= csrf_field() ?><input type="hidden" name="to" value="closed">
        <input type="hidden" name="note" value="Cancelled by requester">
        <button type="submit" class="btn-ghost text-sm"><?= icon('x', 'h-4 w-4') ?> I no longer need help</button>
    </form>
<?php endif; ?>
