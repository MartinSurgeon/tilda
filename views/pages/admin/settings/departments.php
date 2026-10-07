<?php
/** @var array $departments @var array $usage */
echo App\Core\View::partial('pages/admin/settings/_tabs', ['tab' => 'departments']);
?>
<div class="mb-6 flex justify-end">
    <details class="w-full sm:w-auto" data-disclosure>
        <summary class="btn-primary w-full cursor-pointer list-none sm:w-auto"><?= icon('plus') ?> Add department</summary>
        <form method="post" action="<?= e(url('/admin/settings/departments')) ?>" class="card card-body mt-3 grid gap-3 sm:w-[28rem] sm:grid-cols-[1fr_8rem]">
            <?= csrf_field() ?>
            <div>
                <label for="new-name" class="label">Name</label>
                <input id="new-name" name="name" class="input" required maxlength="100">
            </div>
            <div>
                <label for="new-code" class="label">Short code</label>
                <input id="new-code" name="code" class="input uppercase" required maxlength="20" placeholder="e.g. ENT">
            </div>
            <button type="submit" class="btn-primary sm:col-span-2">Add</button>
        </form>
    </details>
</div>

<section class="card overflow-hidden" aria-label="Departments">
    <?php foreach ($departments as $d): $people = (int) ($usage[$d['id']] ?? 0); ?>
        <details class="group border-b border-line last:border-b-0" data-disclosure>
            <summary class="flex min-h-touch cursor-pointer list-none items-center gap-3 px-4 py-2 hover:bg-smoke-2 sm:px-6">
                <span class="badge-neutral font-mono"><?= e($d['code']) ?></span>
                <span class="flex-1 text-ink"><?= e($d['name']) ?></span>
                <?php if (!(int) $d['is_active']): ?><span class="badge-neutral">Hidden</span><?php endif; ?>
                <span class="hidden text-sm text-muted sm:inline"><?= $people ?> <?= $people === 1 ? 'person' : 'people' ?></span>
                <span class="flex items-center gap-1 text-sm font-medium text-muted">
                    Edit <?= icon('chevron-down', 'h-4 w-4 transition-transform duration-150 group-open:rotate-180') ?>
                </span>
            </summary>
            <form method="post" action="<?= e(url('/admin/settings/departments/' . $d['id'])) ?>" class="grid gap-3 border-t border-line bg-smoke-2 px-4 py-4 sm:grid-cols-[1fr_10rem] sm:px-6">
                <?= csrf_field() ?>
                <div>
                    <label class="label" for="dn-<?= (int) $d['id'] ?>">Name</label>
                    <input id="dn-<?= (int) $d['id'] ?>" name="name" class="input" value="<?= e($d['name']) ?>" required maxlength="100">
                </div>
                <div>
                    <label class="label" for="dc-<?= (int) $d['id'] ?>">Short code</label>
                    <input id="dc-<?= (int) $d['id'] ?>" name="code" class="input uppercase" value="<?= e($d['code']) ?>" required maxlength="20">
                </div>
                <label class="flex min-h-touch items-center gap-3 sm:col-span-2">
                    <input type="checkbox" name="is_active" value="1" class="checkbox" <?= (int) $d['is_active'] ? 'checked' : '' ?>>
                    <span class="text-sm"><span class="font-semibold text-ink">Active</span>. Untick to hide it from forms. History is kept.</span>
                </label>
                <div class="sm:col-span-2"><button type="submit" class="btn-primary">Save</button></div>
            </form>
        </details>
    <?php endforeach; ?>
</section>
