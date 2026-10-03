<?php /** @var string $tab */ ?>
<header class="mb-5">
    <h1 class="page-title">Settings</h1>
    <p class="page-subtitle">Lists staff choose from, and the service targets IT works to. Every change is recorded in the audit log.</p>
</header>
<nav class="-mx-4 mb-6 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Settings sections">
    <ul class="flex min-w-max gap-2" role="list">
        <?php foreach (['categories' => 'Categories', 'departments' => 'Departments', 'priorities' => 'Priorities & SLA'] as $key => $label): $current = $tab === $key; ?>
            <li>
                <a href="<?= e(url('/admin/settings/' . $key)) ?>" <?= $current ? 'aria-current="page"' : '' ?>
                   class="inline-flex min-h-touch items-center rounded-full border px-4 text-sm font-semibold no-underline hover:no-underline <?= $current ? 'border-teal bg-teal text-white' : 'border-line bg-white text-charcoal hover:bg-smoke' ?>"><?= e($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
