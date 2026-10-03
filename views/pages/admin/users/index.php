<?php
/** @var array $users @var array $roles @var array $filters @var int $page @var int $pages @var int $total */
$temp = App\Core\Session::pull('_temp_password');
$roleBadge = ['employee' => 'badge-neutral', 'technician' => 'badge-teal', 'it_manager' => 'badge-midnight',
              'management' => 'badge-info', 'auditor' => 'badge-warning'];
$isLocked = static fn (array $u) => $u['locked_until'] !== null && strtotime($u['locked_until']) > time();
$hasFilters = $filters['q'] !== '' || $filters['role'] > 0 || $filters['status'] !== '';
?>
<header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="page-title">Users</h1>
        <p class="page-subtitle"><?= (int) $total ?> <?= $total === 1 ? 'person' : 'people' ?><?= $hasFilters ? ' match your filters' : '' ?></p>
    </div>
    <a href="<?= e(url('/admin/users/create')) ?>" class="btn-primary"><?= icon('plus') ?> Add user</a>
</header>

<?php if ($temp): ?>
    <div class="alert-info mb-6" role="status">
        <?= icon('key', 'mt-0.5 h-5 w-5 shrink-0') ?>
        <div class="flex-1">
            <p class="font-semibold">Temporary password for <?= e($temp['user']) ?></p>
            <p class="mt-1">Give it to them in person or by phone. It is shown only once, and they must change it when they sign in.</p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <code class="rounded-md bg-white px-3 py-2 font-mono text-base text-ink" id="temp-password"><?= e($temp['password']) ?></code>
                <button type="button" class="btn-secondary" data-copy="temp-password">Copy</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<form method="get" action="<?= e(url('/admin/users')) ?>" class="card card-body mb-6 grid gap-3 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end" role="search">
    <div>
        <label for="q" class="label">Search</label>
        <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" class="input" placeholder="Name or email">
    </div>
    <div>
        <label for="role" class="label">Role</label>
        <select id="role" name="role" class="select">
            <option value="">All roles</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?= (int) $r['id'] ?>" <?= $filters['role'] === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="status" class="label">Status</label>
        <select id="status" name="status" class="select">
            <option value="">Any</option>
            <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Deactivated</option>
        </select>
    </div>
    <div class="flex gap-2">
        <button type="submit" class="btn-secondary flex-1"><?= icon('search') ?> Search</button>
        <?php if ($hasFilters): ?><a href="<?= e(url('/admin/users')) ?>" class="btn-ghost">Clear</a><?php endif; ?>
    </div>
</form>

<?php if (!$users): ?>
    <div class="card">
        <?= App\Core\View::partial('components/empty-state', [
            'icon' => 'users', 'title' => 'No users found',
            'text' => 'Try a different name or clear the filters.',
        ]) ?>
    </div>
<?php else: ?>
    <!-- Desktop table -->
    <div class="card hidden overflow-x-auto lg:block">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Role</th>
                    <th scope="col" class="hidden xl:table-cell">Department</th>
                    <th scope="col">Last sign-in</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <span class="avatar" aria-hidden="true"><?= e(initials($u['full_name'])) ?></span>
                            <div class="min-w-0">
                                <p class="font-semibold text-ink"><?= e($u['full_name']) ?></p>
                                <p class="truncate text-muted"><?= e($u['email']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td><span class="<?= $roleBadge[$u['role']] ?? 'badge-neutral' ?>"><?= e($u['role_name']) ?></span></td>
                    <td class="hidden xl:table-cell"><?= e($u['department_name'] ?? '—') ?></td>
                    <td><?= e($u['last_login_at'] ? fmt_relative($u['last_login_at']) : 'Never') ?></td>
                    <td><?= App\Core\View::partial('pages/admin/users/_status', ['u' => $u, 'locked' => $isLocked($u)]) ?></td>
                    <td class="text-right">
                        <a href="<?= e(url('/admin/users/' . $u['id'] . '/edit')) ?>" class="btn-ghost" aria-label="Edit <?= e($u['full_name']) ?>"><?= icon('edit', 'h-4 w-4') ?> Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile cards -->
    <ul class="space-y-3 lg:hidden" role="list">
        <?php foreach ($users as $u): ?>
            <li>
                <a href="<?= e(url('/admin/users/' . $u['id'] . '/edit')) ?>" class="card flex items-start gap-3 p-4 text-charcoal no-underline hover:no-underline">
                    <span class="avatar" aria-hidden="true"><?= e(initials($u['full_name'])) ?></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-ink"><?= e($u['full_name']) ?></p>
                        <p class="truncate text-sm text-muted"><?= e($u['email']) ?></p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span class="<?= $roleBadge[$u['role']] ?? 'badge-neutral' ?>"><?= e($u['role_name']) ?></span>
                            <?= App\Core\View::partial('pages/admin/users/_status', ['u' => $u, 'locked' => $isLocked($u)]) ?>
                        </div>
                    </div>
                    <?= icon('chevron-right', 'h-5 w-5 shrink-0 text-muted') ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?= App\Core\View::partial('components/pagination', [
        'page' => $page, 'pages' => $pages, 'path' => '/admin/users',
        'query' => ['q' => $filters['q'], 'role' => $filters['role'] ?: null, 'status' => $filters['status']],
    ]) ?>
<?php endif; ?>
