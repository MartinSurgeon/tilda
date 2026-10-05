<?php $me = user(); ?>
<div class="mx-auto max-w-3xl space-y-6">
    <header>
        <h1 class="page-title">My account</h1>
        <p class="page-subtitle">Your details are managed by the IT team. Ask them if anything here is wrong.</p>
    </header>

    <section class="card" aria-labelledby="profile-title">
        <div class="card-header"><h2 id="profile-title" class="card-title">Profile</h2></div>
        <dl class="card-body grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm text-muted">Name</dt><dd class="font-medium text-ink"><?= e($me['full_name']) ?></dd></div>
            <div><dt class="text-sm text-muted">Email</dt><dd class="break-all font-medium text-ink"><?= e($me['email']) ?></dd></div>
            <div><dt class="text-sm text-muted">Department</dt><dd class="font-medium text-ink"><?= e($me['department_name'] ?? '—') ?></dd></div>
            <div><dt class="text-sm text-muted">Role</dt><dd class="font-medium text-ink"><?= e($me['role_name']) ?></dd></div>
        </dl>
    </section>

    <section class="card" aria-labelledby="notify-title">
        <div class="card-header"><h2 id="notify-title" class="card-title">Email notifications</h2></div>
        <form method="post" action="<?= e(url('/account/notifications')) ?>" class="card-body space-y-4">
            <?= csrf_field() ?>
            <p class="text-sm">You always see updates under <a href="<?= e(url('/notifications')) ?>">Notifications</a>. Choose what you also want by email to <strong class="text-ink"><?= e($me['email']) ?></strong>.</p>
            <?php if (!$emailEnabled): ?>
                <div class="alert-warning"><?= icon('info', 'mt-0.5 h-5 w-5 shrink-0') ?><p>Email is currently switched off for the whole system. Your choices are saved for when it is turned on.</p></div>
            <?php endif; ?>
            <fieldset>
                <legend class="sr-only">Email me when</legend>
                <div class="space-y-1">
                    <?php foreach ($prefs as $event => $p): ?>
                        <label class="flex min-h-touch cursor-pointer items-center gap-3 rounded-lg px-2 hover:bg-smoke-2">
                            <input type="checkbox" name="email[<?= e($event) ?>]" value="1" class="checkbox" <?= $p['email'] ? 'checked' : '' ?>>
                            <span class="text-sm text-ink"><?= e($p['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <button type="submit" class="btn-secondary">Save email settings</button>
        </form>
    </section>

    <section class="card" id="sound" aria-labelledby="sound-title">
        <div class="card-header"><h2 id="sound-title" class="card-title">Sound</h2></div>
        <form method="post" action="<?= e(url('/account/sound')) ?>" class="card-body space-y-4">
            <?= csrf_field() ?>
            <p class="text-sm">A soft chime when a new notification arrives while this system is open, even in a background tab.
                Urgent updates (Critical tickets and SLA warnings) play a brighter chime twice. The chimes are deliberately unlike medical alarms.</p>
            <label class="flex min-h-touch cursor-pointer items-center gap-3 rounded-lg px-2 hover:bg-smoke-2">
                <input type="checkbox" name="sound" value="1" class="checkbox" <?= notification_sound_on() ? 'checked' : '' ?>>
                <span class="text-sm text-ink">Play a sound for new notifications</span>
            </label>
            <div class="flex flex-col gap-3 sm:flex-row">
                <button type="submit" class="btn-secondary">Save sound setting</button>
                <button type="button" class="btn-ghost border border-line" data-test-sound="normal"><?= icon('bell') ?> Play normal chime</button>
                <button type="button" class="btn-ghost border border-line" data-test-sound="urgent"><?= icon('alert-triangle') ?> Play urgent chime</button>
            </div>
            <p class="hint">If you hear nothing, check that this browser tab is not muted and your device volume is up.</p>
        </form>
    </section>

    <section class="card" aria-labelledby="security-title">
        <div class="card-header"><h2 id="security-title" class="card-title">Security</h2></div>
        <div class="card-body flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm">Change your password regularly and never share it.</p>
            <a href="<?= e(url('/account/password')) ?>" class="btn-secondary"><?= icon('key') ?> Change password</a>
        </div>
    </section>
</div>
