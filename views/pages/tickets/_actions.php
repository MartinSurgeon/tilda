<?php
/**
 * "Next step" panel: one primary action, the rest tucked under "More actions".
 * Everything shown here is re-checked on the server when submitted.
 */
$statusUrl = url('/tickets/' . $t['id'] . '/status');
$itMoves = array_filter($transitions, static fn ($x) => $x['as'] === 'it');
$primaryKey = isset($itMoves['in_progress']) ? 'in_progress' : (isset($itMoves['resolved']) ? 'resolved' : null);
$secondary = array_diff_key($itMoves, array_flip(array_filter([$primaryKey])));
$resolution = null;
foreach (array_reverse($history) as $h) {
    if ($h['to_status'] === 'resolved') { $resolution = $h; break; }
}

/** Renders a status change; transitions that need a note open an inline form. */
$move = static function (string $to, array $x, bool $primary) use ($statusUrl, $t): string {
    $btn = $primary ? 'btn-primary' : 'btn-secondary';
    $label = $to === 'in_progress' && $t['status'] === 'on_hold' ? 'Resume work' : $x['label'];
    ob_start();
    if (!$x['note']): ?>
        <form method="post" action="<?= e($statusUrl) ?>">
            <?= csrf_field() ?><input type="hidden" name="to" value="<?= e($to) ?>">
            <button type="submit" class="<?= $btn ?> w-full sm:w-auto" data-loading-text="Saving…"><?= icon($x['icon']) ?> <?= e($label) ?></button>
        </form>
    <?php else: $id = 'note-' . $to; ?>
        <details class="group w-full" data-disclosure>
            <summary class="<?= $btn ?> w-full cursor-pointer list-none sm:w-auto"><?= icon($x['icon']) ?> <?= e($label) ?></summary>
            <form method="post" action="<?= e($statusUrl) ?>" class="mt-3 space-y-3 rounded-lg border border-line bg-smoke-2 p-4" novalidate data-validate>
                <?= csrf_field() ?><input type="hidden" name="to" value="<?= e($to) ?>">
                <div>
                    <label for="<?= e($id) ?>" class="label"><?= e($x['prompt']) ?></label>
                    <textarea id="<?= e($id) ?>" name="note" class="textarea" rows="3" maxlength="500" required
                              aria-describedby="<?= e($id) ?>-hint" data-msg-required="Please add a short explanation."></textarea>
                    <p id="<?= e($id) ?>-hint" class="hint"><?= e($x['hint']) ?></p>
                </div>
                <button type="submit" class="<?= $to === 'reopened' && $x['as'] === 'requester' ? 'btn-secondary' : 'btn-primary' ?>" data-loading-text="Saving…">Confirm: <?= e(mb_strtolower($label)) ?></button>
            </form>
        </details>
    <?php endif;
    return (string) ob_get_clean();
};
?>

<?php if ($isRequester && $t['status'] === 'resolved' && isset($transitions['closed'])): ?>
    <!-- Requester confirms the fix (peak-end: close the loop clearly). -->
    <section class="card border-teal" aria-labelledby="confirm-title">
        <div class="card-body space-y-4">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-tint text-green-text"><?= icon('check-circle') ?></span>
                <div>
                    <h2 id="confirm-title" class="text-lg font-semibold">IT says this is fixed. Is it?</h2>
                    <?php if ($resolution && $resolution['note'] !== ''): ?>
                        <p class="mt-1 whitespace-pre-line text-sm"><span class="font-semibold text-ink"><?= e($resolution['full_name']) ?>:</span> <?= e($resolution['note']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                <?= $move('closed', $transitions['closed'], true) ?>
                <?php if (isset($transitions['reopened'])): ?><?= $move('reopened', $transitions['reopened'], false) ?><?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($canAccept || $itMoves || $canAssign || $canRelease || $canPriority): ?>
    <section class="card" aria-labelledby="next-title">
        <div class="card-header"><h2 id="next-title" class="card-title">Next step</h2></div>
        <div class="card-body space-y-4">

            <?php if ($canAccept): ?>
                <p class="text-sm">Nobody is handling this yet.</p>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <form method="post" action="<?= e(url('/tickets/' . $t['id'] . '/accept')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-primary w-full sm:w-auto" data-loading-text="Taking ticket…"><?= icon('user-check') ?> Take this ticket</button>
                    </form>
                </div>
            <?php elseif ($primaryKey): ?>
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start">
                    <?= $move($primaryKey, $itMoves[$primaryKey], true) ?>
                    <?php foreach ($secondary as $to => $x): if ($to === 'closed') { continue; } ?>
                        <?= $move($to, $x, false) ?>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($t['status'] === 'resolved' && !$isRequester): ?>
                <p class="text-sm">Waiting for <?= e($t['requester_name']) ?> to confirm the fix. It closes automatically after <?= (int) setting('tickets.auto_close_days', '5') ?> days.</p>
            <?php elseif (!$t['assignee_id'] && !$canAccept): ?>
                <p class="text-sm">Waiting for a technician to take this ticket.</p>
            <?php endif; ?>

            <?php
            // Moves not already shown above (e.g. Close, or Reopen on a resolved ticket).
            $moreMoves = $primaryKey ? array_intersect_key($secondary, ['closed' => 1]) : $secondary;
            if ($canAssign || $canRelease || $canPriority || $moreMoves): ?>
                <details class="border-t border-line pt-3" data-disclosure <?= !$canAccept && !$primaryKey && $canAssign ? 'open' : '' ?>>
                    <summary class="inline-flex min-h-touch cursor-pointer items-center gap-2 text-sm font-semibold text-midnight"><?= icon('more', 'h-4 w-4') ?> More actions</summary>
                    <div class="mt-3 space-y-5">
                        <?php if ($canAssign): ?>
                            <form method="post" action="<?= e(url('/tickets/' . $t['id'] . '/assign')) ?>" class="grid gap-2 sm:grid-cols-[1fr_auto] sm:items-end">
                                <?= csrf_field() ?>
                                <div>
                                    <label for="assignee_id" class="label"><?= $t['assignee_id'] ? 'Reassign to' : 'Assign to' ?></label>
                                    <select id="assignee_id" name="assignee_id" class="select" required>
                                        <option value="">Choose a technician…</option>
                                        <?php foreach ($technicians as $tech): if ((int) $tech['id'] === (int) $t['assignee_id']) { continue; } ?>
                                            <option value="<?= (int) $tech['id'] ?>"><?= e($tech['full_name']) ?> (<?= (int) $tech['open_count'] ?> open)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn-secondary"><?= icon('user-check') ?> Assign</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($canPriority): ?>
                            <form method="post" action="<?= e(url('/tickets/' . $t['id'] . '/priority')) ?>" class="space-y-2" novalidate data-validate>
                                <?= csrf_field() ?>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <label for="priority_id" class="label">Change priority</label>
                                        <select id="priority_id" name="priority_id" class="select">
                                            <?php foreach ($priorities as $p): ?>
                                                <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === (int) $t['priority_id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="priority-reason" class="label">Reason</label>
                                        <input id="priority-reason" name="reason" class="input" maxlength="300" required data-msg-required="Please say why the priority is changing.">
                                    </div>
                                </div>
                                <button type="submit" class="btn-secondary">Update priority</button>
                            </form>
                        <?php endif; ?>

                        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start">
                            <?php if ($canRelease): ?>
                                <form method="post" action="<?= e(url('/tickets/' . $t['id'] . '/release')) ?>"
                                      data-confirm="Return this ticket to the queue? Someone else will need to take it." data-confirm-label="Return to queue">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn-ghost w-full border border-line sm:w-auto"><?= icon('rotate-ccw') ?> Return to queue</button>
                                </form>
                            <?php endif; ?>
                            <?php foreach ($moreMoves as $to => $x): ?>
                                <?= $move($to, $x, false) ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    </section>
<?php elseif ($isRequester && !in_array($t['status'], ['resolved', 'closed'], true)): ?>
    <section class="card card-body flex items-start gap-3" aria-label="Progress">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-info-tint text-info-text"><?= icon('clock') ?></span>
        <p class="text-sm">
            <?php if ($t['assignee_name']): ?>
                <strong class="text-ink"><?= e($t['assignee_name']) ?></strong> is handling your ticket. You will be notified of any update.
            <?php else: ?>
                Your ticket is waiting for a technician. You will be notified as soon as someone picks it up.
            <?php endif; ?>
        </p>
    </section>
<?php endif; ?>
