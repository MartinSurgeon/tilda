<?php
/** @var array $groups */
$primary = bottom_nav_items();
$me = user();
?>
<!-- Mobile bottom navigation: ≤ 5 targets, "New ticket" in the thumb-friendly centre. -->
<nav class="pb-safe fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white lg:hidden" aria-label="Main navigation">
    <ul class="mx-auto flex max-w-lg items-stretch" role="list">
        <?php foreach ($primary as $item): ?>
            <li class="flex flex-1">
                <a href="<?= e(url($item['path'])) ?>" class="bottom-nav-link" <?= nav_is_active($item['path']) ? 'aria-current="page"' : '' ?>>
                    <?= icon($item['icon'], 'h-6 w-6') ?><span><?= e($item['label'] === 'Dashboard' ? 'Home' : $item['label']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>

        <?php if (can('ticket.create')): ?>
            <li class="flex flex-1 justify-center">
                <a href="<?= e(url('/tickets/create')) ?>" class="bottom-nav-link">
                    <span class="-mt-5 flex h-14 w-14 items-center justify-center rounded-full bg-teal text-white shadow-raised ring-4 ring-white">
                        <?= icon('plus', 'h-7 w-7') ?>
                    </span>
                    <span class="font-semibold text-teal-darker">New ticket</span>
                </a>
            </li>
        <?php endif; ?>

        <li class="flex flex-1">
            <a href="<?= e(url('/notifications')) ?>" class="bottom-nav-link relative" <?= nav_is_active('/notifications') ? 'aria-current="page"' : '' ?>>
                <span class="relative">
                    <?= icon('bell', 'h-6 w-6') ?>
                    <span data-bell-count class="absolute -right-2 -top-1 min-w-[1.1rem] rounded-full bg-danger px-1 text-center text-[10px] font-bold leading-[1.1rem] text-white <?= unread_notifications() ? '' : 'hidden' ?>"><?= unread_notifications() > 99 ? '99+' : unread_notifications() ?></span>
                </span>
                <span>Alerts</span>
            </a>
        </li>

        <li class="flex flex-1">
            <button type="button" class="bottom-nav-link" data-open-dialog="more-menu" aria-haspopup="dialog">
                <?= icon('menu', 'h-6 w-6') ?><span>More</span>
            </button>
        </li>
    </ul>
</nav>

<dialog id="more-menu" class="m-0 mt-auto w-full max-w-none rounded-t-2xl p-0 backdrop:bg-ink/40 lg:hidden" aria-labelledby="more-menu-title">
    <div class="flex items-center justify-between border-b border-line px-4 py-3">
        <h2 id="more-menu-title" class="text-base font-semibold">Menu</h2>
        <button type="button" class="btn-icon" data-close-dialog aria-label="Close menu"><?= icon('x') ?></button>
    </div>
    <div class="max-h-[70vh] overflow-y-auto">
        <?= App\Core\View::partial('partials/nav', ['groups' => $groups]) ?>
        <div class="border-t border-line px-3 py-3">
            <a href="<?= e(url('/account')) ?>" class="nav-link"><?= icon('user') ?> My account · <?= e($me['full_name']) ?></a>
            <form method="post" action="<?= e(url('/logout')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="nav-link w-full"><?= icon('logout') ?> Sign out</button>
            </form>
        </div>
    </div>
</dialog>
