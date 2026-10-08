<?php
/** @var array $filters @var array $rows @var int $total @var int $page @var int $pages @var array $users @var array $actions @var array $entities */
use App\Core\View;

echo View::partial('pages/audit/_header', ['tab' => 'activity', 'filters' => $filters]);
$f = $filters;
$filterCount = count(array_filter([$f['from'], $f['to'], $f['user'], $f['action'], $f['entity'], $f['id'], $f['ip']]));

/** Colour of the small dot before each entry: what kind of event it was. */
$dot = static fn (string $a) => match (true) {
    str_contains($a, 'failed') || str_contains($a, 'denied') || str_contains($a, 'locked') || str_contains($a, 'breached') || str_contains($a, 'throttled') => 'bg-danger-fg',
    str_starts_with($a, 'audit.') || str_starts_with($a, 'settings.') || str_starts_with($a, 'user.') => 'bg-warning',
    str_starts_with($a, 'auth.') || str_starts_with($a, 'account.') => 'bg-midnight',
    default => 'bg-line-strong',
};

/** Nested metadata as dotted "filters.month" => "2026-10" pairs. */
$flatten = static function (array $data, string $prefix = '') use (&$flatten): array {
    $out = [];
    foreach ($data as $k => $v) {
        $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
        if (is_array($v)) {
            $out += $v === [] ? [$key => '—'] : $flatten($v, $key);
        } else {
            $out[$key] = is_bool($v) ? ($v ? 'true' : 'false') : (string) ($v ?? '—');
        }
    }
    return $out;
};

// Group by day, and fold a run of identical entries (same person, same action,
// same place, no extra details) into one line with a count. Newest first.
$days = [];
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
foreach ($rows as $r) {
    $meta = $r['metadata'] ? json_decode($r['metadata'], true) : null;
    $d = date('Y-m-d', strtotime($r['created_at']));
    $label = $d === $today ? 'Today' : ($d === $yesterday ? 'Yesterday' : date('j F Y', strtotime($d)));
    $days[$label] ??= [];
    $k = array_key_last($days[$label]);
    if ($k !== null && !$meta && !$days[$label][$k]['meta']) {
        $p = $days[$label][$k]['row'];
        if ($p['action'] === $r['action'] && $p['user_email'] === $r['user_email'] && $p['description'] === $r['description']
            && $p['entity_type'] === $r['entity_type'] && $p['entity_id'] === $r['entity_id'] && $p['ip_address'] === $r['ip_address']) {
            $days[$label][$k]['count']++;
            $days[$label][$k]['first_at'] = $r['created_at'];
            continue;
        }
    }
    $days[$label][] = ['row' => $r, 'meta' => $meta, 'count' => 1, 'first_at' => $r['created_at']];
}
?>
<form method="get" action="<?= e(url('/audit')) ?>" class="mb-4" role="search">
    <div class="flex gap-2">
        <label for="f-q" class="sr-only">Search the activity log</label>
        <input type="search" id="f-q" name="q" value="<?= e($f['q']) ?>" class="input" placeholder="Search description or email">
        <button type="submit" class="btn-secondary shrink-0"><?= icon('search') ?><span class="sr-only sm:not-sr-only">Search</span></button>
    </div>
    <details class="group mt-2" <?= $filterCount ? 'open' : '' ?>>
        <summary class="inline-flex min-h-touch cursor-pointer list-none items-center gap-2 text-sm font-medium text-muted hover:text-ink">
            <?= icon('filter', 'h-4 w-4') ?> Filters<?= $filterCount ? " ({$filterCount} on)" : '' ?>
            <?= icon('chevron-down', 'h-4 w-4 transition-transform duration-150 group-open:rotate-180') ?>
        </summary>
        <div class="card card-body mt-2 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
            <div class="flex items-end gap-2">
                <a href="<?= e(url('/audit')) ?>" class="btn-ghost">Clear</a>
                <button type="submit" class="btn-primary flex-1">Apply filters</button>
            </div>
        </div>
    </details>
    <p class="mt-1 text-sm text-muted"><?= number_format($total) ?> <?= $total === 1 ? 'entry' : 'entries' ?></p>
</form>

<?php if (!$rows): ?>
    <div class="card"><?= View::partial('components/empty-state', ['icon' => 'search', 'title' => 'No entries match', 'text' => 'Try a wider date range or fewer filters.']) ?></div>
<?php else: ?>
    <ol class="card divide-y divide-line overflow-hidden" role="list">
        <?php foreach ($days as $label => $entries): ?>
            <li class="bg-smoke-2 px-4 py-1.5 sm:px-6"><h2 class="text-xs font-semibold uppercase tracking-wide text-muted"><?= e($label) ?></h2></li>
            <?php foreach ($entries as $en): $r = $en['row']; $meta = $en['meta']; $who = $r['full_name'] ?: ($r['user_email'] ?: 'System'); ?>
                <li>
                    <details class="group">
                        <summary class="flex min-h-touch cursor-pointer list-none items-start gap-3 px-4 py-2.5 hover:bg-smoke-2/70 sm:px-6">
                            <time class="mt-0.5 w-[4.25rem] shrink-0 text-xs tabular-nums text-muted" datetime="<?= e(date('c', strtotime($r['created_at']))) ?>"><?= e(date('H:i:s', strtotime($r['created_at']))) ?></time>
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full <?= $dot($r['action']) ?>" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm text-ink"><?= e($r['description']) ?>
                                    <?php if ($en['count'] > 1): ?><span class="badge-neutral ml-1 tabular-nums">×<?= (int) $en['count'] ?></span><?php endif; ?>
                                </span>
                                <span class="block truncate text-xs text-muted"><?= e($who) ?></span>
                            </span>
                            <?= icon('chevron-down', 'mt-1 h-4 w-4 shrink-0 text-muted transition-transform duration-150 group-open:rotate-180') ?>
                        </summary>
                        <div class="mx-4 mb-3 rounded-lg border border-line bg-smoke-2 p-3 sm:ml-[6.25rem] sm:mr-6">
                            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                                <dt class="text-xs text-muted">Who</dt><dd class="min-w-0 break-words text-ink"><?= e($r['full_name'] ? $r['full_name'] . ' · ' . $r['user_email'] : ($r['user_email'] ?: 'System')) ?></dd>
                                <dt class="text-xs text-muted">Action</dt><dd class="font-mono text-xs text-ink"><?= e($r['action']) ?></dd>
                                <?php if ($r['entity_type']): ?><dt class="text-xs text-muted">About</dt><dd class="text-ink"><?= e($r['entity_type'] . ' #' . $r['entity_id']) ?></dd><?php endif; ?>
                                <dt class="text-xs text-muted">From</dt><dd class="text-ink"><?= e($r['ip_address'] ?? '—') ?></dd>
                                <?php if ($en['count'] > 1): ?><dt class="text-xs text-muted">Repeated</dt><dd class="text-ink"><?= (int) $en['count'] ?> times, <?= e(date('H:i:s', strtotime($en['first_at']))) ?> to <?= e(date('H:i:s', strtotime($r['created_at']))) ?></dd><?php endif; ?>
                                <?php if ($meta): foreach ($flatten($meta) as $k => $v): ?>
                                    <dt class="font-mono text-xs text-muted"><?= e($k) ?></dt><dd class="min-w-0 break-words text-ink"><?= e($v) ?></dd>
                                <?php endforeach; endif; ?>
                            </dl>
                            <p class="mt-3 break-all border-t border-line pt-2 font-mono text-xs text-muted">Entry #<?= (int) $r['id'] ?> · seal <?= e(substr($r['row_hash'], 0, 16)) ?>…</p>
                        </div>
                    </details>
                </li>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </ol>
    <?= View::partial('components/pagination', ['page' => $page, 'pages' => $pages, 'path' => '/audit', 'query' => array_filter($f)]) ?>
<?php endif; ?>
