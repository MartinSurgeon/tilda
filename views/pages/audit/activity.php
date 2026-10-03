<?php
/** @var array $filters @var array $rows @var int $total @var int $page @var int $pages @var array $users @var array $actions @var array $entities */
use App\Core\View;

echo View::partial('pages/audit/_header', ['tab' => 'activity', 'filters' => $filters]);
$f = $filters;
$advanced = count(array_filter([$f['action'], $f['entity'], $f['id'], $f['ip']]));
$tone = static fn (string $a) => match (true) {
    str_contains($a, 'failed') || str_contains($a, 'denied') || str_contains($a, 'locked') || str_contains($a, 'breached') || str_contains($a, 'throttled') => 'badge-danger',
    str_starts_with($a, 'audit.') || str_starts_with($a, 'settings.') || str_starts_with($a, 'user.') => 'badge-warning',
    str_starts_with($a, 'auth.') || str_starts_with($a, 'account.') => 'badge-midnight',
    default => 'badge-neutral',
};
?>
<form method="get" action="<?= e(url('/audit')) ?>" class="card card-body mb-5 space-y-4" role="search">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
            <label for="f-q" class="label">Search</label>
            <input type="search" id="f-q" name="q" value="<?= e($f['q']) ?>" class="input" placeholder="Description or email">
        </div>
    </div>
    <details <?= $advanced ? 'open' : '' ?>>
        <summary class="inline-flex min-h-touch cursor-pointer items-center gap-2 text-sm font-semibold text-midnight"><?= icon('filter', 'h-4 w-4') ?> More filters<?= $advanced ? " ({$advanced} on)" : '' ?></summary>
        <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="f-action" class="label">Action</label>
                <select id="f-action" name="action" class="select">
                    <option value="">Any</option>
                    <?php foreach ($actions as $group => $list): ?>
                        <optgroup label="<?= e(ucfirst($group)) ?>">
                            <?php foreach ($list as $a): ?><option value="<?= e($a) ?>" <?= $f['action'] === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="f-entity" class="label">About</label>
                <select id="f-entity" name="entity" class="select">
                    <option value="">Anything</option>
                    <?php foreach ($entities as $en): ?><option value="<?= e($en) ?>" <?= $f['entity'] === $en ? 'selected' : '' ?>><?= e(ucfirst($en)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="f-id" class="label">Record ID</label>
                <input id="f-id" name="id" value="<?= e($f['id']) ?>" class="input" inputmode="numeric">
            </div>
            <div>
                <label for="f-ip" class="label">IP address</label>
                <input id="f-ip" name="ip" value="<?= e($f['ip']) ?>" class="input">
            </div>
        </div>
    </details>
    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-muted"><?= number_format($total) ?> <?= $total === 1 ? 'entry' : 'entries' ?></p>
        <div class="flex gap-2">
            <a href="<?= e(url('/audit')) ?>" class="btn-ghost">Clear</a>
            <button type="submit" class="btn-primary flex-1 sm:flex-none"><?= icon('search') ?> Search</button>
        </div>
    </div>
</form>

<?php if (!$rows): ?>
    <div class="card"><?= View::partial('components/empty-state', ['icon' => 'search', 'title' => 'No entries match', 'text' => 'Try a wider date range or fewer filters.']) ?></div>
<?php else: ?>
    <ol class="card divide-y divide-line overflow-hidden" role="list">
        <?php foreach ($rows as $r): $meta = $r['metadata'] ? json_decode($r['metadata'], true) : null; ?>
            <li class="px-4 py-3 sm:px-6">
                <div class="flex flex-col gap-1 lg:flex-row lg:items-start lg:gap-4">
                    <time class="shrink-0 text-sm tabular-nums text-muted lg:w-40" datetime="<?= e(date('c', strtotime($r['created_at']))) ?>"><?= e(date('j M Y, H:i:s', strtotime($r['created_at']))) ?></time>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="<?= $tone($r['action']) ?> font-mono"><?= e($r['action']) ?></span>
                            <span class="text-ink"><?= e($r['description']) ?></span>
                        </p>
                        <p class="mt-1 text-sm text-muted">
                            <?= e($r['full_name'] ? $r['full_name'] . ' · ' . $r['user_email'] : ($r['user_email'] ?: 'System')) ?>
                            <?php if ($r['entity_type']): ?> · <?= e($r['entity_type'] . ' #' . $r['entity_id']) ?><?php endif; ?>
                            · <?= e($r['ip_address'] ?? '—') ?>
                        </p>
                        <?php if ($meta): ?>
                            <details class="mt-1">
                                <summary class="inline-flex min-h-touch cursor-pointer items-center text-sm font-semibold text-midnight">Details</summary>
                                <pre class="mt-1 overflow-x-auto whitespace-pre-wrap break-all rounded-lg bg-smoke-2 p-3 text-xs text-ink"><?= e(json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                                <p class="mt-1 break-all text-xs text-muted">Entry #<?= (int) $r['id'] ?> · fingerprint <?= e(substr($r['row_hash'], 0, 16)) ?>…</p>
                            </details>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
    <?= View::partial('components/pagination', ['page' => $page, 'pages' => $pages, 'path' => '/audit', 'query' => array_filter($f)]) ?>
<?php endif; ?>
