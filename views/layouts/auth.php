<?php /** @var string $content @var string $title */ ?>
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0d8257">
    <title><?= e(($title ?? 'Sign in') . ' · ' . config('name')) ?></title>
    <link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="h-full bg-white">
<a class="skip-link" href="#main">Skip to main content</a>
<div class="flex min-h-full">
    <!-- Brand panel (desktop) -->
    <div class="relative hidden w-[44%] flex-col justify-between bg-brand-linear-1 p-12 text-white lg:flex">
        <div class="flex items-center gap-3">
            <img src="<?= e(asset('img/logo-mark.svg')) ?>" alt="" class="h-11 w-11 rounded-xl ring-2 ring-white/30">
            <span class="text-lg font-bold">RUMA Hospital</span>
        </div>
        <div>
            <h2 class="text-3xl font-bold leading-tight text-white">IT Support &amp; Maintenance</h2>
            <ul class="mt-6 space-y-3 text-base text-white" role="list">
                <li class="flex gap-3"><?= icon('check-circle', 'h-6 w-6 shrink-0 text-light-green') ?> Report a problem in under a minute</li>
                <li class="flex gap-3"><?= icon('check-circle', 'h-6 w-6 shrink-0 text-light-green') ?> Follow progress as it happens</li>
                <li class="flex gap-3"><?= icon('check-circle', 'h-6 w-6 shrink-0 text-light-green') ?> Every action recorded for compliance</li>
            </ul>
        </div>
        <p class="text-sm text-white">ruma.hospital · Internal use only</p>
    </div>

    <main id="main" class="flex flex-1 flex-col justify-center px-4 py-10 sm:px-8">
        <div class="mx-auto w-full max-w-sm">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <img src="<?= e(asset('img/logo-mark.svg')) ?>" alt="" class="h-10 w-10">
                <span class="leading-tight">
                    <span class="block text-lg font-bold text-ink">RUMA Hospital</span>
                    <span class="block text-sm text-muted">IT Support</span>
                </span>
            </div>
            <?= App\Core\View::partial('partials/flash') ?>
            <?= $content ?>
        </div>
    </main>
</div>
</body>
</html>
