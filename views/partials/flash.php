<?php
// Background refreshes must not consume messages meant for the visible page.
$flashes = App\Core\Request::isBackground() ? [] : App\Core\Session::takeFlashes();
$icons = ['success' => 'check-circle', 'info' => 'info', 'warning' => 'alert-triangle', 'danger' => 'alert-circle'];
?>
<?php if ($flashes): ?>
    <div class="mb-6 space-y-3">
        <?php foreach ($flashes as $flash): $type = isset($icons[$flash['type']]) ? $flash['type'] : 'info'; ?>
            <div class="alert-<?= e($type) ?>" role="<?= $type === 'danger' || $type === 'warning' ? 'alert' : 'status' ?>" data-flash>
                <?= icon($icons[$type], 'mt-0.5 h-5 w-5 shrink-0') ?>
                <p class="flex-1"><?= e($flash['message']) ?></p>
                <button type="button" class="-my-2 -mr-2 inline-flex h-11 w-11 items-center justify-center rounded-lg hover:bg-white/60" data-dismiss aria-label="Dismiss message">
                    <?= icon('x', 'h-4 w-4') ?>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
