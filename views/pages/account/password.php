<?php /** @var bool $forced @var string[] $rules */ ?>
<div class="mx-auto max-w-xl">
    <header class="mb-6">
        <h1 class="page-title"><?= $forced ? 'Choose your password' : 'Change password' ?></h1>
        <p class="page-subtitle">
            <?= $forced
                ? 'For your security, replace the temporary password before you continue.'
                : 'Pick something long that you do not use anywhere else.' ?>
        </p>
    </header>

    <form method="post" action="<?= e(url('/account/password')) ?>" class="card card-body space-y-5" novalidate data-validate>
        <?= csrf_field() ?>
        <!-- Lets password managers store the new password against the right account. -->
        <input type="email" autocomplete="username" value="<?= e(user()['email']) ?>" class="hidden" readonly tabindex="-1" aria-hidden="true">

        <div>
            <label for="current_password" class="label"><?= $forced ? 'Temporary password' : 'Current password' ?></label>
            <input type="password" class="input" <?= field_attrs('current_password') ?> autocomplete="current-password" required
                   autofocus data-msg-required="Please enter your <?= $forced ? 'temporary' : 'current' ?> password.">
            <?= field_error('current_password') ?>
        </div>

        <div>
            <label for="password" class="label">New password</label>
            <div class="relative">
                <input type="password" class="input pr-12" <?= field_attrs('password', true) ?> autocomplete="new-password" required
                       minlength="<?= App\Services\PasswordPolicy::MIN_LENGTH ?>" data-password-meter="password-rules"
                       data-msg-required="Please choose a new password.">
                <button type="button" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-muted hover:text-ink"
                        data-toggle-password="password" aria-label="Show password" aria-pressed="false">
                    <?= icon('eye') ?>
                </button>
            </div>
            <ul id="password-hint" class="mt-2 space-y-1 text-sm" data-rules-list="password-rules" role="list">
                <li class="flex items-center gap-2 text-muted" data-rule="length"><?= icon('circle-dot', 'h-4 w-4') ?> <span><?= e($rules[0]) ?></span></li>
                <li class="flex items-center gap-2 text-muted" data-rule="mix"><?= icon('circle-dot', 'h-4 w-4') ?> <span><?= e($rules[1]) ?></span></li>
                <li class="flex items-center gap-2 text-muted"><?= icon('info', 'h-4 w-4') ?> <span><?= e($rules[2]) ?></span></li>
            </ul>
            <?= field_error('password') ?>
        </div>

        <div>
            <label for="password_confirmation" class="label">Type the new password again</label>
            <input type="password" class="input" <?= field_attrs('password_confirmation') ?> autocomplete="new-password" required
                   data-match="password" data-msg-required="Please type your new password again." data-msg-match="The two passwords do not match.">
            <?= field_error('password_confirmation') ?>
        </div>

        <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
            <?php if (!$forced): ?>
                <a href="<?= e(url('/account')) ?>" class="btn-ghost">Cancel</a>
            <?php endif; ?>
            <button type="submit" class="btn-primary" data-loading-text="Saving…"><?= icon('lock') ?> Save password</button>
        </div>
    </form>

    <?php if ($forced): ?>
        <form method="post" action="<?= e(url('/logout')) ?>" class="mt-4 text-center">
            <?= csrf_field() ?>
            <button type="submit" class="btn-ghost text-sm">Not you? Sign out</button>
        </form>
    <?php endif; ?>
</div>
