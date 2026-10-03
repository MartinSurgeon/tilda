<?php
/** @var array $t @var int $responseMinutes */
use App\Services\Sla;
?>
<div class="mx-auto max-w-xl py-4 text-center">
    <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-tint text-green-text"><?= icon('check', 'h-8 w-8') ?></span>
    <h1 class="mt-5 text-2xl font-bold">Your ticket has been sent</h1>
    <p class="mt-2 text-charcoal">Reference <strong class="font-mono text-ink"><?= e($t['ref']) ?></strong></p>

    <div class="card card-body mt-8 text-left">
        <h2 class="card-title">What happens next</h2>
        <ol class="mt-4 space-y-4" role="list">
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-tint text-sm font-bold text-teal-darker">1</span>
                <p>The IT team has been notified<?= $responseMinutes ? ' and will respond within <strong class="text-ink">' . e(Sla::humanMinutes($responseMinutes)) . '</strong>' : '' ?>.</p>
            </li>
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-tint text-sm font-bold text-teal-darker">2</span>
                <p>You will get a notification when someone picks it up or replies.</p>
            </li>
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-tint text-sm font-bold text-teal-darker">3</span>
                <p>When it is fixed, we will ask you to confirm.</p>
            </li>
        </ol>
        <?php if ($t['tone'] === 'critical'): ?>
            <div class="alert-danger mt-5">
                <?= icon('alert-triangle', 'mt-0.5 h-5 w-5 shrink-0') ?>
                <p>Marked as <strong>Critical</strong>. If patient care is at risk right now, also phone the IT help desk.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
        <a href="<?= e(url('/tickets/' . $t['id'])) ?>" class="btn-primary">View my ticket</a>
        <a href="<?= e(url('/')) ?>" class="btn-secondary">Back to dashboard</a>
    </div>
</div>
