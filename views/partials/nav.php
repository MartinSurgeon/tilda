<?php /** @var array $groups */ ?>
<nav class="flex-1 overflow-y-auto px-3 py-4">
    <?php foreach ($groups as $name => $items): ?>
        <div class="mb-5 last:mb-0">
            <h2 class="section-title mb-2 px-3 text-xs"><?= e($name) ?></h2>
            <ul class="space-y-1" role="list">
                <?php foreach ($items as $item): ?>
                    <li>
                        <a href="<?= e(url($item['path'])) ?>" class="nav-link" <?= nav_is_active($item['path']) ? 'aria-current="page"' : '' ?>>
                            <?= icon($item['icon']) ?>
                            <span class="flex-1"><?= e($item['label']) ?></span>
                            <?php if ($item['path'] === '/notifications'): /* kept in sync live by app.js */ ?>
                                <span class="badge-danger" <?= unread_notifications() ? '' : 'hidden' ?>><span data-bell-count-nav><?= unread_notifications() ?></span><span class="sr-only"> unread</span></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</nav>
