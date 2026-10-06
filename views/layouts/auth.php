<?php /** @var string $content @var string $title */ ?>
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0d8257">
    <title><?= e(($title ?? 'Sign in') . ' · ' . config('name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= e(asset('fonts/sora.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="h-full bg-surface">
<a class="skip-link" href="#main">Skip to main content</a>
<div class="flex min-h-full">
    <!-- Brand panel (desktop) -->
    <aside class="relative hidden w-[44%] flex-col justify-between bg-brand-linear-1 p-12 text-white lg:flex" aria-label="About this service">
        <div>
            <img src="<?= e(asset('img/logo-mark.png')) ?>" alt="RUMA IT Support" class="h-16 w-auto max-w-[320px] object-contain drop-shadow-md">
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
    </aside>

    <main id="main" class="flex flex-1 flex-col justify-center px-4 py-10 sm:px-8">
        <div class="mx-auto w-full max-w-sm">
            <div class="mb-8 lg:hidden">
                <img src="<?= e(asset('img/logo-mark.png')) ?>" alt="RUMA IT Support" class="h-12 w-auto max-w-[240px] object-contain">
            </div>
            <?= App\Core\View::partial('partials/flash') ?>
            <?= $content ?>
        </div>
    </main>
</div>
</body>
</html>
