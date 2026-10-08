<?php /** @var string $tab */ ?>
<header class="mb-5">
    <h1 class="page-title">Settings</h1>
    <p class="page-subtitle">Lists staff choose from, and the service targets IT works to. Every change is recorded in the audit log.</p>
</header>
<nav class="-mx-4 mb-6 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Settings sections">
    <ul class="tab-strip min-w-max" role="list">
        <?php foreach (['categories' => 'Categories', 'departments' => 'Departments', 'priorities' => 'Priorities & SLA'] as $key => $label): $current = $tab === $key; ?>
            <li>
                <a href="<?= e(url('/admin/settings/' . $key)) ?>" <?= $current ? 'aria-current="page"' : '' ?>
                   class="tab"><?= e($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
