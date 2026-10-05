<?php
/** @var ?array $user @var array $roles @var array $departments */
$editing = $user !== null;
$val = static fn (string $field, mixed $default = '') => old($field, $editing ? ($user[$field] ?? $default) : $default);
$selfEdit = $editing && (int) $user['id'] === App\Core\Auth::id();
$locked = $editing && $user['locked_until'] !== null && strtotime($user['locked_until']) > time();
$active = errors() ? old('is_active', '0') === '1' : (!$editing || (int) $user['is_active'] === 1);
?>
<div class="mx-auto max-w-3xl">
    <a href="<?= e(url('/admin/users')) ?>" class="mb-4 inline-flex min-h-touch items-center gap-1 text-sm font-semibold"><?= icon('chevron-left', 'h-4 w-4') ?> All users</a>

    <header class="mb-6">
        <h1 class="page-title"><?= $editing ? e($user['full_name']) : 'Add user' ?></h1>
        <p class="page-subtitle"><?= $editing
            ? 'Changes are recorded in the audit log.'
            : 'A temporary password is created for them. They choose their own when they first sign in.' ?></p>
    </header>

    <?php if (errors()): ?>
        <div class="alert-danger mb-6" role="alert" tabindex="-1" data-error-summary>
            <?= icon('alert-circle', 'mt-0.5 h-5 w-5 shrink-0') ?>
            <p>Please fix the <?= count(errors()) === 1 ? 'highlighted field' : count(errors()) . ' highlighted fields' ?> below.</p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url($editing ? '/admin/users/' . $user['id'] : '/admin/users')) ?>" class="space-y-6" novalidate data-validate>
        <?= csrf_field() ?>

        <fieldset class="card">
            <legend class="sr-only">Person</legend>
            <div class="card-header"><h2 class="card-title">Person</h2></div>
            <div class="card-body grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="full_name" class="label">Full name</label>
                    <input type="text" class="input" <?= field_attrs('full_name') ?> value="<?= e($val('full_name')) ?>" required maxlength="120"
                           autocomplete="off" data-msg-required="Please enter their full name.">
                    <?= field_error('full_name') ?>
                </div>
                <div class="sm:col-span-2">
                    <label for="email" class="label">Work email</label>
                    <input type="email" class="input" <?= field_attrs('email') ?> value="<?= e($val('email')) ?>" required maxlength="190"
                           autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="name@ruma.hospital"
                           data-msg-required="Please enter their work email." data-msg-type="That email address does not look right.">
                    <?= field_error('email') ?>
                </div>
                <div>
                    <label for="job_title" class="label">Job title <span class="label-optional">(optional)</span></label>
                    <input type="text" class="input" <?= field_attrs('job_title') ?> value="<?= e($val('job_title')) ?>" maxlength="100">
                    <?= field_error('job_title') ?>
                </div>
                <div>
                    <label for="phone" class="label">Phone or extension <span class="label-optional">(optional)</span></label>
                    <input type="tel" class="input" <?= field_attrs('phone') ?> value="<?= e($val('phone')) ?>" maxlength="30" inputmode="tel">
                    <?= field_error('phone') ?>
                </div>
            </div>
        </fieldset>

        <fieldset class="card">
            <legend class="sr-only">Access</legend>
            <div class="card-header"><h2 class="card-title">Access</h2></div>
            <div class="card-body space-y-5">
                <?php if ($selfEdit): ?>
                    <div>
                        <p class="label">Role</p>
                        <p class="font-medium text-ink"><?= e(user()['role_name']) ?></p>
                        <p class="hint">You cannot change your own role or deactivate yourself. Ask another administrator.</p>
                        <input type="hidden" name="role_id" value="<?= (int) $user['role_id'] ?>">
                        <input type="hidden" name="is_active" value="1">
                    </div>
                <?php else: ?>
                <div role="radiogroup" aria-labelledby="role-label" <?= error('role_id') ? 'aria-describedby="role_id-error"' : '' ?>>
                    <p id="role-label" class="label">Role</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <?php foreach ($roles as $r): $checked = (string) $val('role_id', '1') === (string) $r['id']; ?>
                            <label class="flex cursor-pointer gap-3 rounded-lg border p-3 has-[:checked]:border-teal has-[:checked]:bg-teal-tint <?= $checked ? 'border-teal' : 'border-line' ?>">
                                <input type="radio" name="role_id" value="<?= (int) $r['id'] ?>" class="mt-0.5 h-5 w-5 border-line-strong text-teal focus:ring-teal/30"
                                       <?= $checked ? 'checked' : '' ?>>
                                <span>
                                    <span class="block font-semibold text-ink"><?= e($r['name']) ?></span>
                                    <span class="block text-sm text-muted"><?= e($r['description']) ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?= field_error('role_id') ?>
                </div>
                <?php endif; ?>

                <div>
                    <label for="department_id" class="label">Department</label>
                    <select class="select" <?= field_attrs('department_id') ?>>
                        <option value="">No department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int) $d['id'] ?>" <?= (string) $val('department_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error('department_id') ?>
                </div>

                <?php if (!$selfEdit): ?>
                    <label class="flex min-h-touch items-center gap-3">
                        <input type="checkbox" name="is_active" value="1" class="checkbox" <?= $active ? 'checked' : '' ?>>
                        <span>
                            <span class="block font-semibold text-ink">Account active</span>
                            <span class="block text-sm text-muted">Untick to stop this person signing in. Their history is kept.</span>
                        </span>
                    </label>
                <?php endif; ?>
            </div>
        </fieldset>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="<?= e(url('/admin/users')) ?>" class="btn-ghost">Cancel</a>
            <button type="submit" class="btn-primary" data-loading-text="Saving…"><?= icon('check') ?> <?= $editing ? 'Save changes' : 'Add user' ?></button>
        </div>
    </form>

    <?php if ($editing && !$selfEdit): ?>
        <section class="card mt-8" aria-labelledby="security-title">
            <div class="card-header"><h2 id="security-title" class="card-title">Sign-in help</h2></div>
            <div class="card-body space-y-4">
                <?php if ($locked): ?>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm"><span class="font-semibold text-danger-fg">Locked</span> after too many failed sign-ins, until <?= e(fmt_date($user['locked_until'], 'H:i')) ?>.</p>
                        <form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/unlock')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-secondary"><?= icon('unlock') ?> Unlock now</button>
                        </form>
                    </div>
                <?php endif; ?>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm">Forgotten password? Create a new temporary one.</p>
                    <form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/reset-password')) ?>"
                          data-confirm="Reset the password for <?= e($user['full_name']) ?>? Their current password will stop working immediately.">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-secondary"><?= icon('key') ?> Reset password</button>
                    </form>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
