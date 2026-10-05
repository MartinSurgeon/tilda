<?php
/**
 * One-click switch between day and night-shift mode (account menu / More menu).
 * For "match device", app.js sets the right label and value from the device setting.
 */
$theme = user()['theme'] ?? 'system';
$toNight = $theme !== 'dark';
?>
<form method="post" action="<?= e(url('/account/theme')) ?>">
    <?= csrf_field() ?>
    <button type="submit" name="theme" value="<?= $toNight ? 'dark' : 'light' ?>" class="nav-link w-full" data-theme-toggle data-theme-current="<?= e($theme) ?>">
        <?= icon('moon', 'h-5 w-5 ' . ($toNight ? '' : 'hidden')) ?><?= icon('sun', 'h-5 w-5 ' . ($toNight ? 'hidden' : '')) ?>
        <span data-theme-toggle-label><?= $toNight ? 'Night-shift mode' : 'Day mode' ?></span>
    </button>
</form>
