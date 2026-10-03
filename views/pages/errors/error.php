<?php
/** @var int $status @var string $title @var ?Throwable $debug */
$help = match ($status) {
    403 => 'Your role does not include this area. If you think you need access, ask the IT Manager.',
    404 => 'The link may be out of date, or the item may have been removed.',
    419 => 'For your security, forms expire after a while. Go back, refresh the page and try again.',
    405 => 'Go back and try again from the page you were on.',
    default => 'The problem has been logged. Please try again in a moment. If it keeps happening, contact the IT help desk.',
};
$signedIn = session_status() === PHP_SESSION_ACTIVE && App\Core\Auth::check();
?>
<div class="mx-auto max-w-lg py-10 text-center">
    <p class="text-sm font-semibold text-muted">Error <?= (int) $status ?></p>
    <h1 class="mt-2 text-2xl font-bold"><?= e($title) ?></h1>
    <p class="mt-3 text-charcoal"><?= e($help) ?></p>
    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
        <a href="<?= e(url($signedIn ? '/' : '/login')) ?>" class="btn-primary"><?= $signedIn ? 'Go to dashboard' : 'Go to sign in' ?></a>
    </div>
    <?php if ($debug): ?>
        <pre class="mt-8 overflow-x-auto rounded-lg bg-ink p-4 text-left text-xs text-white"><?= e($debug::class . ': ' . $debug->getMessage() . "\n" . $debug->getFile() . ':' . $debug->getLine() . "\n\n" . $debug->getTraceAsString()) ?></pre>
    <?php endif; ?>
</div>
