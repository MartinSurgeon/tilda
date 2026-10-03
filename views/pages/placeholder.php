<?php /** @var string $title @var string $icon @var int $phase */ ?>
<header class="mb-6">
    <h1 class="page-title"><?= e($title) ?></h1>
</header>
<div class="card">
    <?= App\Core\View::partial('components/empty-state', [
        'icon'   => $icon,
        'title'  => 'Arriving in build phase ' . $phase,
        'text'   => 'This page is part of the plan and your access to it is already checked. Its content is built in a later phase.',
        'action' => ['Back to dashboard', url('/')],
    ]) ?>
</div>
