<?php
/** @var array $priorities */
use App\Services\Sla;
use App\Services\TicketMeta;

echo App\Core\View::partial('pages/admin/settings/_tabs', ['tab' => 'priorities']);
?>
<form method="post" action="<?= e(url('/admin/settings/priorities')) ?>" class="space-y-4"
      data-confirm="Save the new target times? They apply to new tickets straight away." data-confirm-label="Save targets">
    <?= csrf_field() ?>
    <div class="alert-info">
        <?= icon('info', 'mt-0.5 h-5 w-5 shrink-0') ?>
        <p><strong>Response</strong> is how soon IT must first act on a ticket. <strong>Resolution</strong> is how soon it must be fixed.
           Time on hold is not counted. A ticket shows as “at risk” once the warning point is reached.</p>
    </div>

    <?php foreach ($priorities as $p): $id = (int) $p['id']; ?>
        <fieldset class="card">
            <legend class="sr-only"><?= e($p['name']) ?></legend>
            <div class="card-header">
                <?= TicketMeta::priorityBadge($p['name'], $p['tone'], $p['icon']) ?>
                <span class="text-sm text-muted">Now: respond in <?= e(Sla::humanMinutes((int) $p['response_minutes'])) ?>, fix in <?= e(Sla::humanMinutes((int) $p['resolution_minutes'])) ?></span>
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label class="label" for="pn-<?= $id ?>">Name</label>
                    <input id="pn-<?= $id ?>" name="p[<?= $id ?>][name]" class="input" value="<?= e($p['name']) ?>" required maxlength="40">
                </div>
                <div class="sm:col-span-2 lg:col-span-2">
                    <label class="label" for="pd-<?= $id ?>">When to use it</label>
                    <input id="pd-<?= $id ?>" name="p[<?= $id ?>][description]" class="input" value="<?= e($p['description']) ?>" maxlength="255">
                </div>
                <div>
                    <label class="label" for="pr-<?= $id ?>">Response (minutes)</label>
                    <input id="pr-<?= $id ?>" name="p[<?= $id ?>][response_minutes]" type="number" inputmode="numeric" min="1" max="10080" class="input" value="<?= (int) $p['response_minutes'] ?>" required>
                </div>
                <div>
                    <label class="label" for="ph-<?= $id ?>">Resolution (hours)</label>
                    <input id="ph-<?= $id ?>" name="p[<?= $id ?>][resolution_hours]" type="number" inputmode="decimal" min="0.25" max="1440" step="0.25" class="input" value="<?= e(rtrim(rtrim(number_format($p['resolution_minutes'] / 60, 2, '.', ''), '0'), '.')) ?>" required>
                </div>
                <div>
                    <label class="label" for="pw-<?= $id ?>">Warn at (% of time)</label>
                    <input id="pw-<?= $id ?>" name="p[<?= $id ?>][warn_percent]" type="number" inputmode="numeric" min="50" max="95" class="input" value="<?= (int) $p['warn_percent'] ?>" required>
                </div>
            </div>
        </fieldset>
    <?php endforeach; ?>

    <div class="flex justify-end">
        <button type="submit" class="btn-primary"><?= icon('check') ?> Save targets</button>
    </div>
</form>
