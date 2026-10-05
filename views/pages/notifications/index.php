<?php
/** @var array $notifications @var bool $unreadOnly @var int $unread @var int $page @var int $pages */
$icons = [
    'ticket.created' => ['plus', 'bg-info-tint text-info-text'],
    'ticket.assigned' => ['user-check', 'bg-teal-tint text-teal-darker'],
    'ticket.status' => ['refresh', 'bg-midnight-tint text-midnight'],
    'ticket.comment' => ['inbox', 'bg-midnight-tint text-midnight'],
    'ticket.resolved' => ['check-circle', 'bg-green-tint text-green-text'],
    'ticket.sla_risk' => ['alert-triangle', 'bg-danger-tint text-danger-fg'],
];
// Group by day so the list is scannable (Today / Yesterday / date).
$groups = [];
foreach ($notifications as $n) {
    $d = date('Y-m-d', strtotime($n['created_at']));
    $label = $d === date('Y-m-d') ? 'Today' : ($d === date('Y-m-d', strtotime('-1 day')) ? 'Yesterday' : date('j F Y', strtotime($d)));
    $groups[$label][] = $n;
}
?>
<header class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="page-title">Notifications</h1>
        <p class="page-subtitle" data-unread-text><?= $unread ? $unread . ' unread' : 'You are all caught up' ?></p>
    </div>
    <!-- Always rendered; shown or hidden live as the unread count changes. -->
    <form method="post" action="<?= e(url('/notifications/read-all')) ?>" data-unread-action <?= $unread ? '' : 'hidden' ?>>
        <?= csrf_field() ?>
        <button type="submit" class="btn-secondary w-full sm:w-auto"><?= icon('check') ?> Mark all as read</button>
    </form>
</header>

<nav class="mb-4" aria-label="Filter notifications">
    <ul class="flex gap-2" role="list">
        <?php foreach (['' => 'All', 'unread' => 'Unread'] as $key => $label): $current = ($key === 'unread') === $unreadOnly; ?>
            <li>
                <a href="<?= e(url('/notifications', ['show' => $key])) ?>" <?= $current ? 'aria-current="page"' : '' ?>
                   class="inline-flex min-h-touch items-center rounded-full border px-4 text-sm font-semibold no-underline hover:no-underline <?= $current ? 'border-teal bg-teal text-white' : 'border-line bg-surface text-charcoal hover:bg-smoke' ?>"><?= e($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<div id="live-notifications" data-live>
<?php if (!$notifications): ?>
    <div class="card">
        <?= App\Core\View::partial('components/empty-state', $unreadOnly
            ? ['icon' => 'check-circle', 'title' => 'No unread notifications', 'text' => 'New updates about your tickets will appear here.']
            : ['icon' => 'bell', 'title' => 'No notifications yet', 'text' => 'You will be notified here when something happens on your tickets.']) ?>
    </div>
<?php else: ?>
    <div class="space-y-6">
        <?php foreach ($groups as $label => $items): ?>
            <section aria-label="<?= e($label) ?>">
                <h2 class="section-title mb-2"><?= e($label) ?></h2>
                <ul class="card divide-y divide-line overflow-hidden" role="list" data-kb-list>
                    <?php foreach ($items as $n): [$ico, $tone] = $icons[$n['event']] ?? ['bell', 'bg-smoke text-muted']; $isUnread = $n['read_at'] === null; ?>
                        <li data-kb-item>
                            <a href="<?= e(url('/notifications/' . $n['id'])) ?>" class="flex min-h-touch items-start gap-3 px-4 py-3 text-charcoal no-underline hover:bg-smoke-2 hover:no-underline sm:px-6 <?= $isUnread ? 'bg-teal-tint/40' : '' ?>">
                                <span class="stat-icon h-9 w-9 <?= $tone ?>"><?= icon($ico, 'h-4 w-4') ?></span>
                                <div class="min-w-0 flex-1">
                                    <p class="<?= $isUnread ? 'font-semibold text-ink' : 'text-ink' ?>"><?= e($n['title']) ?></p>
                                    <?php if ($n['body'] !== ''): ?><p class="mt-0.5 line-clamp-2 text-sm text-muted"><?= e($n['body']) ?></p><?php endif; ?>
                                    <p class="mt-1 text-xs text-muted"><?= e(fmt_relative($n['created_at'])) ?></p>
                                </div>
                                <?php if ($isUnread): ?>
                                    <span class="badge-teal mt-1 shrink-0">New<span class="sr-only">, unread</span></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>
    <?= App\Core\View::partial('components/pagination', ['page' => $page, 'pages' => $pages, 'path' => '/notifications', 'query' => ['show' => $unreadOnly ? 'unread' : null]]) ?>
<?php endif; ?>
</div>
