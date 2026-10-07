<?php
/** @var array $categories @var array $subcategories @var array $icons @var array $usage */
use App\Core\View;

echo View::partial('pages/admin/settings/_tabs', ['tab' => 'categories']);

/** One editable row (category or sub-category). */
$row = static function (array $c, bool $top) use ($icons, $usage): string {
    $used = (int) ($usage[$c['id']] ?? 0);
    ob_start(); ?>
    <details class="group" data-disclosure>
        <summary class="flex min-h-touch cursor-pointer list-none items-center gap-3 px-4 py-2 hover:bg-smoke-2 sm:px-6 <?= $top ? '' : 'bg-smoke-2/60 pl-10 sm:pl-14' ?>">
            <?php if ($top): ?><?= icon($c['icon'], 'h-5 w-5 shrink-0 text-teal-darker') ?><?php endif; ?>
            <span class="flex-1 <?= $top ? 'font-semibold text-ink' : 'text-charcoal' ?>"><?= e($c['name']) ?></span>
            <?php if (!(int) $c['is_active']): ?><span class="badge-neutral">Hidden</span><?php endif; ?>
            <span class="hidden text-sm text-muted sm:inline"><?= $used ?> <?= $used === 1 ? 'ticket' : 'tickets' ?></span>
            <span class="flex items-center gap-1 text-sm font-medium text-muted">
                Edit <?= icon('chevron-down', 'h-4 w-4 transition-transform duration-150 group-open:rotate-180') ?>
            </span>
        </summary>
        <form method="post" action="<?= e(url('/admin/settings/categories/' . $c['id'])) ?>" class="grid gap-3 border-t border-line bg-smoke-2 px-4 py-4 sm:grid-cols-2 sm:px-6">
            <?= csrf_field() ?>
            <div>
                <label class="label" for="cn-<?= (int) $c['id'] ?>">Name</label>
                <input id="cn-<?= (int) $c['id'] ?>" name="name" class="input" value="<?= e($c['name']) ?>" required maxlength="100">
            </div>
            <div>
                <label class="label" for="cs-<?= (int) $c['id'] ?>">Display order</label>
                <input id="cs-<?= (int) $c['id'] ?>" name="sort_order" type="number" class="input" value="<?= (int) $c['sort_order'] ?>" min="0" max="999">
            </div>
            <?php if ($top): ?>
                <div>
                    <label class="label" for="cd-<?= (int) $c['id'] ?>">Description</label>
                    <input id="cd-<?= (int) $c['id'] ?>" name="description" class="input" value="<?= e($c['description']) ?>" maxlength="255">
                </div>
                <div>
                    <label class="label" for="ci-<?= (int) $c['id'] ?>">Icon</label>
                    <select id="ci-<?= (int) $c['id'] ?>" name="icon" class="select">
                        <?php foreach ($icons as $i): ?><option value="<?= e($i) ?>" <?= $c['icon'] === $i ? 'selected' : '' ?>><?= e(ucfirst($i)) ?></option><?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="description" value="<?= e($c['description']) ?>">
            <?php endif; ?>
            <label class="flex min-h-touch items-center gap-3 sm:col-span-2">
                <input type="checkbox" name="is_active" value="1" class="checkbox" <?= (int) $c['is_active'] ? 'checked' : '' ?>>
                <span class="text-sm"><span class="font-semibold text-ink">Show on the ticket form</span>. Untick to hide it. Existing tickets keep it.</span>
            </label>
            <div class="sm:col-span-2"><button type="submit" class="btn-primary">Save</button></div>
        </form>
    </details>
    <?php return (string) ob_get_clean();
};
?>
<div class="mb-6 flex justify-end">
    <details class="w-full sm:w-auto" data-disclosure>
        <summary class="btn-primary w-full cursor-pointer list-none sm:w-auto"><?= icon('plus') ?> Add category</summary>
        <form method="post" action="<?= e(url('/admin/settings/categories')) ?>" class="card card-body mt-3 grid gap-3 sm:w-[28rem]">
            <?= csrf_field() ?>
            <div>
                <label for="new-parent" class="label">Where</label>
                <select id="new-parent" name="parent_id" class="select">
                    <option value="">New main category</option>
                    <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>">Inside “<?= e($c['name']) ?>”</option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="new-name" class="label">Name</label>
                <input id="new-name" name="name" class="input" required maxlength="100">
            </div>
            <input type="hidden" name="sort_order" value="99"><input type="hidden" name="icon" value="tag"><input type="hidden" name="description" value="">
            <button type="submit" class="btn-primary">Add</button>
        </form>
    </details>
</div>

<div class="space-y-4">
    <?php foreach ($categories as $c): ?>
        <section class="card overflow-hidden" aria-label="<?= e($c['name']) ?>">
            <?= $row($c, true) ?>
            <?php foreach ($subcategories[(int) $c['id']] ?? [] as $s): ?>
                <div class="border-t border-line"><?= $row($s, false) ?></div>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</div>
