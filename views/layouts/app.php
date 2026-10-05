<?php
/** @var string $content @var string $title */
$me = user(); // set before <html> so the theme renders without a flash of the wrong colours
$groups = nav_groups();
?>
<!doctype html>
<html lang="en" class="h-full"<?= in_array($me['theme'] ?? 'system', ['light', 'dark'], true) ? ' data-theme="' . $me['theme'] . '"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-path" content="<?= e(url('/')) ?>">
    <meta name="theme-color" content="#0d8257">
    <meta name="live-version" content="<?= e(App\Services\LiveVersion::for($me)) ?>">
    <meta name="notify-sound" content="<?= notification_sound_on() ? 'on' : 'off' ?>">
    <meta name="notify-sound-src" content="<?= e(asset('audio/notification.mp3')) ?>">
    <meta name="last-notification" content="<?= (int) App\Core\DB::value('SELECT MAX(id) FROM notifications WHERE user_id = ?', [$me['id']]) ?>">
    <meta name="shortcuts-default" content="<?= can('ticket.work') ? 'on' : 'off' ?>">
    <meta name="poll-interval" content="<?= (int) setting('poll.interval_seconds', '15') ?>">
    <title><?= e(($title ?? 'Dashboard') . ' · ' . config('name')) ?></title>
    <link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="h-full">
<a class="skip-link" href="#main">Skip to main content</a>

<!-- Desktop sidebar -->
<aside class="fixed inset-y-0 left-0 z-30 hidden w-sidebar flex-col border-r border-line bg-surface lg:flex" aria-label="Main navigation">
    <a href="<?= e(url('/')) ?>" class="flex h-16 items-center gap-3 border-b border-line px-5 no-underline hover:no-underline">
        <img src="<?= e(asset('img/logo-mark.svg')) ?>" alt="" class="h-9 w-9">
        <span class="leading-tight">
            <span class="block text-base font-bold text-ink">RUMA Hospital</span>
            <span class="block text-xs text-muted">IT Support</span>
        </span>
    </a>
    <?= App\Core\View::partial('partials/nav', ['groups' => $groups]) ?>
    <div class="border-t border-line p-3">
        <a href="<?= e(url('/account')) ?>" class="nav-link" <?= nav_is_active('/account') ? 'aria-current="page"' : '' ?>>
            <span class="avatar" aria-hidden="true"><?= e(initials($me['full_name'])) ?></span>
            <span class="min-w-0">
                <span class="block truncate font-semibold text-ink"><?= e($me['full_name']) ?></span>
                <span class="block truncate text-xs text-muted"><?= e($me['role_name']) ?></span>
            </span>
        </a>
    </div>
</aside>

<div class="flex min-h-full flex-col lg:pl-sidebar">
    <!-- Top bar -->
    <header class="sticky top-0 z-20 border-b border-line bg-surface/95 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8">
            <a href="<?= e(url('/')) ?>" class="flex items-center gap-2 no-underline hover:no-underline lg:hidden">
                <img src="<?= e(asset('img/logo-mark.svg')) ?>" alt="" class="h-8 w-8">
                <span class="text-base font-bold text-ink">RUMA IT</span>
            </a>
            <p class="hidden truncate text-base font-semibold text-ink lg:block"><?= e($title ?? '') ?></p>
            <!-- Live update status: pulses on each successful update, turns grey when updates fail. -->
            <span class="live-indicator" data-live-indicator data-state="live" role="status" title="Updates automatically">
                <span class="live-dot" aria-hidden="true"></span>
                <span class="live-label" data-live-label>Live</span>
            </span>

            <div class="ml-auto flex items-center gap-1 sm:gap-2">
                <?php if (can('ticket.create')): ?>
                    <a href="<?= e(url('/tickets/create')) ?>" class="btn-primary hidden sm:inline-flex">
                        <?= icon('plus') ?> New ticket
                    </a>
                <?php endif; ?>

                <button type="button" class="btn-secondary min-h-touch px-3" data-sound-blocked hidden
                        title="Your browser holds sound back until you click on the page. Click once to turn it on. To never need this, allow sound for this site: see My account, Sound.">
                    <?= icon('volume-x') ?><span class="hidden sm:inline">Turn on sound</span><span class="sr-only sm:hidden">Turn on notification sound</span>
                </button>
                <a href="<?= e(url('/notifications')) ?>" class="btn-icon relative" data-bell
                   aria-label="Notifications<?= unread_notifications() ? ', ' . unread_notifications() . ' unread' : '' ?>">
                    <?= icon('bell', 'h-6 w-6') ?>
                    <span data-bell-count class="absolute right-1 top-1 min-w-[1.25rem] rounded-full bg-danger px-1 text-center text-[11px] font-bold leading-5 text-white <?= unread_notifications() ? '' : 'hidden' ?>"><?= unread_notifications() > 99 ? '99+' : unread_notifications() ?></span>
                </a>

                <details class="relative hidden lg:block" data-dropdown>
                    <summary class="flex min-h-touch cursor-pointer list-none items-center gap-2 rounded-lg px-2 hover:bg-smoke" aria-label="Account menu">
                        <span class="avatar" aria-hidden="true"><?= e(initials($me['full_name'])) ?></span>
                        <?= icon('chevron-down', 'h-4 w-4') ?>
                    </summary>
                    <div class="absolute right-0 mt-2 w-60 rounded-xl border border-line bg-surface p-2 shadow-raised">
                        <p class="px-3 py-2 text-sm">
                            <span class="block font-semibold text-ink"><?= e($me['full_name']) ?></span>
                            <span class="block truncate text-muted"><?= e($me['email']) ?></span>
                        </p>
                        <a href="<?= e(url('/account')) ?>" class="nav-link"><?= icon('user') ?> My account</a>
                        <?= App\Core\View::partial('partials/theme-toggle') ?>
                        <button type="button" class="nav-link w-full" data-open-dialog="shortcuts-help"><?= icon('key') ?> Keyboard shortcuts</button>
                        <form method="post" action="<?= e(url('/logout')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="nav-link w-full"><?= icon('logout') ?> Sign out</button>
                        </form>
                    </div>
                </details>
            </div>
        </div>
    </header>

    <main id="main" tabindex="-1" class="mx-auto w-full max-w-7xl flex-1 px-4 pb-28 pt-6 sm:px-6 lg:px-8 lg:pb-10">
        <?= App\Core\View::partial('partials/flash') ?>
        <?= $content ?>
    </main>
</div>

<?= App\Core\View::partial('partials/bottom-nav', ['groups' => $groups]) ?>
<?= App\Core\View::partial('partials/shortcuts-help') ?>
</body>
</html>
