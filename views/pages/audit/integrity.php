<?php
/** @var array $chains @var ?array $result @var ?array $last */
echo App\Core\View::partial('pages/audit/_header', ['tab' => 'integrity']);
$labels = ['audit_logs' => 'Who-did-what log', 'db_change_logs' => 'Record change log'];
?>
<div class="mx-auto max-w-4xl space-y-6">

<?php if ($result): $allOk = $result[0]['ok'] && $result[1]['ok']; ?>
    <section class="<?= $allOk ? 'alert-success' : 'alert-danger' ?>" role="<?= $allOk ? 'status' : 'alert' ?>" aria-labelledby="result-title">
        <?= icon($allOk ? 'shield-check' : 'alert-triangle', 'mt-0.5 h-6 w-6 shrink-0') ?>
        <div class="flex-1">
            <h2 id="result-title" class="text-base font-bold <?= $allOk ? 'text-green-text' : 'text-danger-fg' ?>">
                <?= $allOk ? 'All good: no entry has been changed, removed or added' : 'Problem found: someone may have changed the activity log' ?>
            </h2>
            <ul class="mt-2 space-y-1" role="list">
                <?php foreach ($result as $r): ?>
                    <li>
                        <strong><?= e($labels[$r['chain']]) ?>:</strong>
                        <?= number_format($r['checked']) ?> entries checked.
                        <?php if ($r['ok']): ?>All seals match.<?php else: ?>
                            <?= e($r['problem']) ?><?= $r['first_bad_id'] ? ' First affected entry: #' . (int) $r['first_bad_id'] . '.' : '' ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$allOk): ?>
                <p class="mt-3 font-semibold">Do not change anything. Tell the IT Manager and compliance officer, and keep a database backup from before this date.</p>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<section class="card" aria-labelledby="how-title">
    <div class="card-header"><h2 id="how-title" class="card-title">Check that nobody has changed the activity log</h2></div>
    <div class="card-body space-y-4">
        <p>Every entry carries a seal made from its own content and the seal of the entry before it. Changing, deleting or adding any entry breaks every seal after it. The check works them all out again and compares.</p>
        <form method="post" action="<?= e(url('/audit/integrity')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn-primary" data-loading-text="Checking every entry…"><?= icon('shield-check') ?> Run safety check</button>
        </form>
        <?php if ($last): $m = json_decode((string) $last['metadata'], true); $ok = ($m['results'][0]['ok'] ?? false) && ($m['results'][1]['ok'] ?? false); ?>
            <p class="text-sm text-muted">Last check: <?= e(fmt_date($last['created_at'])) ?> by <?= e($last['user_email'] ?? 'system') ?>.
                Result: <span class="<?= $ok ? 'badge-success' : 'badge-danger' ?>"><?= icon($ok ? 'check' : 'alert-triangle', 'h-3.5 w-3.5') ?><?= $ok ? 'Passed' : 'Failed' ?></span></p>
        <?php endif; ?>
    </div>
</section>

<section class="card" aria-labelledby="anchor-title">
    <div class="card-header"><h2 id="anchor-title" class="card-title">Current seals</h2></div>
    <div class="card-body space-y-4">
        <p class="text-sm">A copy of these is emailed to the auditors every day. If someone rewrote the whole log, the seals here would no longer match those earlier emails.</p>
        <dl class="grid gap-4 sm:grid-cols-2">
            <?php foreach ($chains as $c): ?>
                <div class="rounded-lg border border-line p-4">
                    <dt class="font-semibold text-ink"><?= e($c['label']) ?> · <?= number_format($c['rows']) ?> entries</dt>
                    <dd class="mt-1 break-all font-mono text-xs text-ink"><?= e($c['head']) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </div>
</section>
</div>
