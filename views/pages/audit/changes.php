<?php
/** @var array $filters @var array $rows @var int $total @var int $page @var int $pages @var array $users @var array $tables */
use App\Core\View;
use App\Models\Audit;

echo View::partial('pages/audit/_header', ['tab' => 'changes', 'filters' => $filters]);
$f = $filters;
$opBadge = ['INSERT' => 'badge-success', 'UPDATE' => 'badge-midnight', 'DELETE' => 'badge-danger'];
$show = static fn ($v) => $v === null ? '∅' : (is_scalar($v) ? (string) $v : json_encode($v));
?>
<form method="get" action="<?= e(url('/audit/changes')) ?>" class="card card-body mb-5 space-y-4" role="search">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <label for="f-from" class="label">From</label>
            <input type="date" id="f-from" name="from" value="<?= e($f['from']) ?>" class="input">
        </div>
        <div>
            <label for="f-to" class="label">To</label>
            <input type="date" id="f-to" name="to" value="<?= e($f['to']) ?>" class="input">
        </div>
        <div><?= View::partial('pages/audit/_user_select', ['users' => $users, 'selected' => $f['user']]) ?></div>
        <div>
            <label for="f-table" class="label">Table</label>
            <select id="f-table" name="table" class="select">
                <option value="">Any</option>
                <?php foreach ($tables as $t): ?><option value="<?= e($t) ?>" <?= $f['table'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="f-op" class="label">Change</label>
            <select id="f-op" name="op" class="select">
                <option value="">Any</option>
                <?php foreach (Audit::OPERATIONS as $op => $label): ?><option value="<?= e($op) ?>" <?= $f['op'] === $op ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="f-id" class="label">Record ID</label>
            <input id="f-id" name="id" value="<?= e($f['id']) ?>" class="input">
        </div>
    </div>
    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-muted"><?= number_format($total) ?> <?= $total === 1 ? 'change' : 'changes' ?> · every insert, update and delete in the database, including changes made outside the app</p>
        <div class="flex gap-2">
            <a href="<?= e(url('/audit/changes')) ?>" class="btn-ghost">Clear</a>
            <button type="submit" class="btn-primary flex-1 sm:flex-none"><?= icon('search') ?> Search</button>
        </div>
    </div>
</form>

<?php if (!$rows): ?>
    <div class="card"><?= View::partial('components/empty-state', ['icon' => 'search', 'title' => 'No changes match', 'text' => 'Try a wider date range or fewer filters.']) ?></div>
<?php else: ?>
    <ol class="card divide-y divide-line overflow-hidden" role="list">
        <?php foreach ($rows as $r): $diff = Audit::diff($r); $direct = $r['user_id'] === null; ?>
            <li class="px-4 py-3 sm:px-6">
                <div class="flex flex-col gap-1 lg:flex-row lg:items-start lg:gap-4">
                    <time class="shrink-0 text-sm tabular-nums text-muted lg:w-40" datetime="<?= e(date('c', strtotime($r['created_at']))) ?>"><?= e(date('j M Y, H:i:s', strtotime($r['created_at']))) ?></time>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="<?= $opBadge[$r['operation']] ?>"><?= e(Audit::OPERATIONS[$r['operation']]) ?></span>
                            <span class="font-mono text-sm text-ink"><?= e($r['table_name']) ?> #<?= e($r['record_id']) ?></span>
                            <?php if ($direct): ?><span class="badge-warning"><?= icon('alert-triangle', 'h-3.5 w-3.5') ?>Outside the app</span><?php endif; ?>
                        </p>
                        <p class="mt-1 text-sm text-muted">
                            <?= e($r['full_name'] ? $r['full_name'] . ' · ' . $r['email'] : ($r['user_agent'] ?? 'System')) ?> · <?= e($r['ip_address'] ?? '—') ?>
                            · <?= count($diff) ?> <?= count($diff) === 1 ? 'field' : 'fields' ?>
                        </p>
                        <details class="mt-1">
                            <summary class="inline-flex min-h-touch cursor-pointer items-center text-sm font-semibold text-midnight">Show values</summary>
                            <div class="mt-1 overflow-x-auto" tabindex="0" role="region" aria-label="Values for change #<?= (int) $r['id'] ?>">
                                <table class="table text-xs">
                                    <thead><tr><th scope="col">Field</th><?php if ($r['operation'] !== 'INSERT'): ?><th scope="col">Before</th><?php endif; ?><?php if ($r['operation'] !== 'DELETE'): ?><th scope="col">After</th><?php endif; ?></tr></thead>
                                    <tbody>
                                    <?php foreach ($diff as $field => $v): ?>
                                        <tr>
                                            <th scope="row" class="font-mono"><?= e($field) ?></th>
                                            <?php if ($r['operation'] !== 'INSERT'): ?><td class="max-w-xs break-words <?= $r['operation'] === 'UPDATE' ? 'bg-danger-tint/50' : '' ?>"><?= e($show($v['old'])) ?></td><?php endif; ?>
                                            <?php if ($r['operation'] !== 'DELETE'): ?><td class="max-w-xs break-words <?= $r['operation'] === 'UPDATE' ? 'bg-green-tint/60' : '' ?>"><?= e($show($v['new'])) ?></td><?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-1 break-all text-xs text-muted">Change #<?= (int) $r['id'] ?> · fingerprint <?= e(substr($r['row_hash'], 0, 16)) ?>…</p>
                        </details>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
    <?= View::partial('components/pagination', ['page' => $page, 'pages' => $pages, 'path' => '/audit/changes', 'query' => array_filter($f)]) ?>
<?php endif; ?>
