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

    <section class="card" id="display" aria-labelledby="display-title">
        <div class="card-header"><h2 id="display-title" class="card-title">Display</h2></div>
        <form method="post" action="<?= e(url('/account/theme')) ?>" class="card-body space-y-4">
            <?= csrf_field() ?>
            <fieldset>
                <legend class="text-sm">Night-shift mode uses dark, low-glare colours for dimmed wards and night work. Your choice follows you to any computer you sign in on.</legend>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <?php foreach ([
                        'light'  => ['Day', 'sun', 'Light colours'],
                        'dark'   => ['Night shift', 'moon', 'Dark, low-glare colours'],
                        'system' => ['Match device', 'monitor', 'Follows this computer or phone'],
                    ] as $value => [$label, $ico, $hint]): $checked = ($me['theme'] ?? 'system') === $value; ?>
                        <label class="flex min-h-touch cursor-pointer items-start gap-3 rounded-lg border p-3 has-[:checked]:border-teal has-[:checked]:bg-teal-tint <?= $checked ? 'border-teal' : 'border-line' ?>">
                            <input type="radio" name="theme" value="<?= $value ?>" class="mt-0.5 h-5 w-5 border-line-strong text-teal focus:ring-teal/30" <?= $checked ? 'checked' : '' ?>>
                            <span>
                                <span class="flex items-center gap-2 font-semibold text-ink"><?= icon($ico, 'h-4 w-4') ?><?= e($label) ?></span>
                                <span class="block text-sm text-muted"><?= e($hint) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <button type="submit" class="btn-secondary">Save display</button>
        </form>
    </section>

    <section class="card" id="sound" aria-labelledby="sound-title">
        <div class="card-header"><h2 id="sound-title" class="card-title">Sound</h2></div>
        <form method="post" action="<?= e(url('/account/sound')) ?>" class="card-body space-y-4">
            <?= csrf_field() ?>
            <p class="text-sm">Plays the notification sound when something new arrives while this system is open, even in a background tab.
                Urgent updates (Critical tickets and SLA warnings) play it twice, louder.</p>
            <label class="flex min-h-touch cursor-pointer items-center gap-3 rounded-lg px-2 hover:bg-smoke-2">
                <input type="checkbox" name="sound" value="1" class="checkbox" <?= notification_sound_on() ? 'checked' : '' ?>>
                <span class="text-sm text-ink">Play a sound for new notifications</span>
            </label>
            <div class="flex flex-col gap-3 sm:flex-row">
                <button type="submit" class="btn-secondary">Save sound setting</button>
                <button type="button" class="btn-ghost border border-line" data-test-sound="normal"><?= icon('volume') ?> Play normal sound</button>
                <button type="button" class="btn-ghost border border-line" data-test-sound="urgent"><?= icon('alert-triangle') ?> Play urgent sound</button>
            </div>
            <?php $site = (App\Core\Request::isHttps() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'); ?>
            <details class="rounded-lg border border-line bg-smoke-2 p-4" open>
                <summary class="flex min-h-touch cursor-pointer items-center font-semibold text-ink">Make it ring automatically, without clicking</summary>
                <div class="mt-2 space-y-3 text-sm">
                    <p>Web browsers block sound on a page until you click or type on it. This rule belongs to the browser, so the system cannot switch it off. Until you change it, a <strong>Turn on sound</strong> button appears next to the bell, and one click turns sound on for that page.</p>
                    <p>To have sound play as soon as a ticket arrives, allow it once on this computer:</p>
                    <ol class="list-decimal space-y-1 pl-5">
                        <li><strong>Microsoft Edge:</strong> open <code class="font-mono">edge://settings/content/mediaAutoplay</code>, then under <em>Allow</em> choose <em>Add</em> and paste the address below.</li>
                        <li><strong>Google Chrome:</strong> Chrome has no per-site switch for this. Ask IT to add the address to the <em>AutoplayAllowlist</em> policy.</li>
                        <li>Reload this page. The <strong>Turn on sound</strong> button should no longer appear.</li>
                    </ol>
                    <div class="flex flex-wrap items-center gap-2">
                        <code id="site-address" class="rounded-md bg-surface px-3 py-2 font-mono text-ink"><?= e($site) ?></code>
                        <button type="button" class="btn-secondary" data-copy="site-address">Copy address</button>
                    </div>
                    <p class="text-muted">If you still hear nothing, check that the browser tab is not muted and the volume is up.</p>
                </div>
            </details>
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
