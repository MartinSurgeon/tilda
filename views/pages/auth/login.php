<h1 class="text-2xl font-bold">Sign in</h1>
<p class="mt-1 text-sm text-muted">Use your RUMA Hospital work email.</p>

<?php if (error('form')): ?>
    <div class="alert-danger mt-6" role="alert">
        <?= icon('alert-circle', 'mt-0.5 h-5 w-5 shrink-0') ?>
        <p><?= e(error('form')) ?></p>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/login')) ?>" class="mt-6 space-y-5" novalidate data-validate>
    <?= csrf_field() ?>
    <div>
        <label for="email" class="label">Work email</label>
        <input type="email" class="input" <?= field_attrs('email') ?> value="<?= e(old('email')) ?>"
               autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" required
               placeholder="name@ruma.hospital" <?= old('email') ? '' : 'autofocus' ?>
               data-msg-required="Please enter your work email." data-msg-type="That email address does not look right.">
        <?= field_error('email') ?>
    </div>

    <div>
        <label for="password" class="label">Password</label>
        <div class="relative">
            <input type="password" class="input pr-12" <?= field_attrs('password') ?> autocomplete="current-password" required
                   <?= old('email') ? 'autofocus' : '' ?> data-msg-required="Please enter your password.">
            <button type="button" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-muted hover:text-ink"
                    data-toggle-password="password" aria-label="Show password" aria-pressed="false">
                <?= icon('eye') ?>
            </button>
        </div>
        <?= field_error('password') ?>
    </div>

    <button type="submit" class="btn-primary btn-block text-base" data-loading-text="Signing in…">Sign in</button>
</form>

<p class="mt-6 text-sm text-muted">
    Forgotten your password? Contact the IT help desk and they will reset it for you.
</p>

<?php if (config('env') === 'local'): ?>
    <details class="mt-8 rounded-lg border border-line bg-smoke-2 p-4 text-sm">
        <summary class="flex min-h-touch cursor-pointer items-center font-semibold text-ink">Demo accounts (local only)</summary>
        <p class="mt-2 text-muted">Password for all: <code class="font-mono text-ink">Ruma@2026!</code></p>
        <ul class="mt-2 space-y-1" role="list">
            <li><code>admin@ruma.hospital</code> · IT Manager</li>
            <li><code>tech@ruma.hospital</code> · Technician</li>
            <li><code>employee@ruma.hospital</code> · Employee</li>
            <li><code>management@ruma.hospital</code> · Management</li>
            <li><code>auditor@ruma.hospital</code> · Auditor</li>
        </ul>
    </details>
<?php endif; ?>
