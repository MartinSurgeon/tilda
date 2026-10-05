<?php
/**
 * @var string $view @var array $views @var array $counts @var array $filters @var array $tickets
 * @var int $total @var int $page @var int $pages
 * @var array $categories @var array $departments @var array $priorities @var array $technicians
 */
use App\Services\TicketMeta;

$activeFilters = count(array_filter([$filters['status'], $filters['priority'], $filters['category'], $filters['department'], $filters['assignee']]));
$keep = ['view' => $view] + array_filter($filters, static fn ($v) => $v !== '' && $v !== 0);
// Where a quick "Take" returns to: this exact queue view, filters and page.
$hereQuery = array_filter($keep + ['page' => $page > 1 ? $page : null], static fn ($v) => $v !== null && $v !== '');
$here = '/tickets' . ($hereQuery ? '?' . http_build_query($hereQuery) : ''); // app-relative; Response::redirect adds the base path
$canWork = can('ticket.work');
?>
<header class="mb-5 flex flex-col gap-1">
    <h1 class="page-title">Ticket queue</h1>
    <p class="page-subtitle"><?= (int) $total ?> <?= $total === 1 ? 'ticket' : 'tickets' ?> in this view</p>
</header>

<!-- Views (tabs). Scrolls sideways on small screens instead of wrapping. -->
<nav id="live-tabs" data-live class="-mx-4 mb-4 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Queue views">
    <ul class="flex min-w-max gap-2" role="list">
        <?php foreach ($views as $key => $label):
            $n = $key === 'done' ? null : (int) ($counts[$key] ?? 0);
            $current = $view === $key; ?>
            <li>
                <a href="<?= e(url('/tickets', ['view' => $key])) ?>" <?= $current ? 'aria-current="page"' : '' ?>
                   class="inline-flex min-h-touch items-center gap-2 rounded-full border px-4 text-sm font-semibold no-underline hover:no-underline <?= $current ? 'border-teal bg-teal text-white' : 'border-line bg-white text-charcoal hover:bg-smoke' ?>">
                    <?= e($label) ?>
                    <?php if ($n !== null): ?>
                        <span class="rounded-full px-2 text-xs <?= $current ? 'bg-teal-darker text-white' : ($key === 'overdue' && $n > 0 ? 'bg-danger-tint text-danger' : 'bg-smoke text-ink') ?>"><?= $n ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<form method="get" action="<?= e(url('/tickets')) ?>" class="mb-5" role="search">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <div class="flex gap-2">
        <label for="q" class="sr-only">Search tickets</label>
        <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" class="input" placeholder="Search by reference or summary">
        <button type="submit" class="btn-secondary shrink-0"><?= icon('search') ?><span class="sr-only sm:not-sr-only">Search</span></button>
    </div>

    <details class="mt-3" <?= $activeFilters ? 'open' : '' ?>>
        <summary class="inline-flex min-h-touch cursor-pointer items-center gap-2 text-sm font-semibold text-midnight">
            <?= icon('filter', 'h-4 w-4') ?> More filters<?= $activeFilters ? " ({$activeFilters} on)" : '' ?>
        </summary>
        <div class="card card-body mt-2 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="f-status" class="label">Status</label>
                <select id="f-status" name="status" class="select">
                    <option value="">Any</option>
                    <?php foreach (TicketMeta::STATUSES as $k => $m): ?>
                        <option value="<?= e($k) ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= e($m['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="f-priority" class="label">Priority</label>
                <select id="f-priority" name="priority" class="select">
                    <option value="">Any</option>
                    <?php foreach ($priorities as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= $filters['priority'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="f-category" class="label">Category</label>
                <select id="f-category" name="category" class="select">
                    <option value="">Any</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $filters['category'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="f-department" class="label">Department</label>
                <select id="f-department" name="department" class="select">
                    <option value="">Any</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int) $d['id'] ?>" <?= $filters['department'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="f-assignee" class="label">Technician</label>
                <select id="f-assignee" name="assignee" class="select">
                    <option value="">Anyone</option>
                    <option value="none" <?= $filters['assignee'] === 'none' ? 'selected' : '' ?>>Unassigned</option>
                    <?php foreach ($technicians as $tech): ?>
                        <option value="<?= (int) $tech['id'] ?>" <?= $filters['assignee'] === (int) $tech['id'] ? 'selected' : '' ?>><?= e($tech['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2 sm:col-span-2 lg:col-span-5 lg:justify-end">
                <a href="<?= e(url('/tickets', ['view' => $view])) ?>" class="btn-ghost">Clear</a>
                <button type="submit" class="btn-secondary">Apply filters</button>
            </div>
        </div>
    </details>
</form>

<div id="live-results" data-live>
<?php if (!$tickets): ?>
    <div class="card">
        <?= App\Core\View::partial('components/empty-state', match (true) {
            $filters['q'] !== '' || $activeFilters > 0 => ['icon' => 'search', 'title' => 'No tickets match', 'text' => 'Try other words or clear the filters.'],
            $view === 'mine' => ['icon' => 'check-circle', 'title' => 'Nothing assigned to you', 'text' => 'Check the Unassigned tab for tickets waiting for someone.'],
            $view === 'unassigned' => ['icon' => 'check-circle', 'title' => 'Every ticket has an owner', 'text' => 'New tickets will appear here as they come in.'],
            $view === 'overdue' => ['icon' => 'check-circle', 'title' => 'Nothing overdue', 'text' => 'All open tickets are within their SLA targets.'],
            default => ['icon' => 'inbox', 'title' => 'No tickets here', 'text' => 'Tickets will appear here as staff report problems.'],
        }) ?>
    </div>
<?php else: ?>
    <div class="card hidden overflow-x-auto lg:block">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Ticket</th>
                    <th scope="col">Priority</th>
                    <th scope="col">Status</th>
                    <th scope="col">Assigned to</th>
                    <th scope="col">SLA</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td class="max-w-md">
                        <a href="<?= e(url('/tickets/' . $t['id'])) ?>" class="font-semibold text-ink"><?= e($t['title']) ?></a>
                        <p class="text-muted"><span class="font-mono"><?= e($t['ref']) ?></span> · <?= e($t['requester_name']) ?>, <?= e($t['department_name']) ?> · <?= e(fmt_relative($t['created_at'])) ?></p>
                    </td>
                    <td><?= TicketMeta::priorityBadge($t['priority_name'], $t['tone'], $t['priority_icon']) ?></td>
                    <td><?= TicketMeta::statusBadge($t['status']) ?></td>
                    <td>
                        <?php if ($t['assignee_name']): ?>
                            <?= e($t['assignee_name']) ?>
                        <?php elseif ($canWork && in_array($t['status'], ['open', 'reopened'], true)): ?>
                            <form method="post" action="<?= e(url('/tickets/' . $t['id'] . '/accept')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return" value="<?= e($here) ?>">
                                <button type="submit" class="btn-secondary" aria-label="Take <?= e($t['ref']) ?>" data-loading-text="Taking…"><?= icon('user-check', 'h-4 w-4') ?> Take</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td><?= TicketMeta::slaBadge($t) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <ul class="space-y-3 lg:hidden" role="list">
        <?php foreach ($tickets as $t): ?>
            <li><?= App\Core\View::partial('pages/tickets/_card', ['t' => $t, 'staff' => true, 'take' => $here]) ?></li>
        <?php endforeach; ?>
    </ul>

    <?= App\Core\View::partial('components/pagination', ['page' => $page, 'pages' => $pages, 'path' => '/tickets', 'query' => $keep]) ?>
<?php endif; ?>
</div>
