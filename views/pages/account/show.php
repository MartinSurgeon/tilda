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

    <section class="card" aria-labelledby="security-title">
        <div class="card-header"><h2 id="security-title" class="card-title">Security</h2></div>
        <div class="card-body flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm">Change your password regularly and never share it.</p>
            <a href="<?= e(url('/account/password')) ?>" class="btn-secondary"><?= icon('key') ?> Change password</a>
        </div>
    </section>
</div>
