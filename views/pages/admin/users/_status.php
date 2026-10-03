<?php /** @var array $u @var bool $locked */ ?>
<?php if ((int) $u['is_active'] !== 1): ?>
    <span class="badge-neutral"><?= icon('x', 'h-3.5 w-3.5') ?>Deactivated</span>
<?php elseif ($locked): ?>
    <span class="badge-danger"><?= icon('lock', 'h-3.5 w-3.5') ?>Locked</span>
<?php elseif ((int) $u['must_change_password'] === 1): ?>
    <span class="badge-warning"><?= icon('key', 'h-3.5 w-3.5') ?>Password pending</span>
<?php else: ?>
    <span class="badge-success"><?= icon('check', 'h-3.5 w-3.5') ?>Active</span>
<?php endif; ?>
