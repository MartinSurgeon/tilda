<?php
/**
 * @var array $t @var array $comments @var array $history @var array $attachments @var array $transitions
 * @var bool $canComment @var bool $canInternal @var bool $canAccept @var bool $canAssign @var bool $canRelease
 * @var bool $canPriority @var array $technicians @var array $priorities
 */
use App\Core\View;
use App\Services\TicketMeta;
use App\Services\TicketPolicy;

$me = user();
$isRequester = TicketPolicy::isRequester($t, $me);
$back = can('ticket.view_all') && !$isRequester ? ['/tickets', 'Ticket queue'] : ['/tickets/mine', 'My tickets'];
$ticketFiles = array_values(array_filter($attachments, static fn ($a) => $a['comment_id'] === null));
$commentFiles = [];
foreach ($attachments as $a) {
    if ($a['comment_id'] !== null) {
        $commentFiles[(int) $a['comment_id']][] = $a;
    }
}
$shared = compact('t', 'transitions', 'canAccept', 'canAssign', 'canRelease', 'canPriority', 'technicians', 'priorities', 'isRequester', 'history');
?>
<a href="<?= e(url($back[0])) ?>" class="mb-3 inline-flex min-h-touch items-center gap-1 text-sm font-semibold"><?= icon('chevron-left', 'h-4 w-4') ?> <?= e($back[1]) ?></a>

<header class="mb-6">
    <p class="font-mono text-sm text-muted"><?= e($t['ref']) ?></p>
    <h1 class="page-title mt-1 break-words"><?= e($t['title']) ?></h1>
    <div class="mt-3 flex flex-wrap items-center gap-2">
        <?= TicketMeta::statusBadge($t['status']) ?>
        <?= TicketMeta::priorityBadge($t['priority_name'], $t['tone'], $t['priority_icon']) ?>
        <?php if (can('ticket.view_all')): ?><?= TicketMeta::slaBadge($t) ?><?php endif; ?>
    </div>
</header>

<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
    <!-- Next step first: the one thing to do now. -->
    <div class="min-w-0 space-y-6 lg:col-start-1">
        <?= View::partial('pages/tickets/_actions', $shared) ?>
    </div>

    <aside class="space-y-6 lg:col-start-2 lg:row-span-2 lg:row-start-1" aria-label="Ticket details">
        <?= View::partial('pages/tickets/_details', $shared) ?>
    </aside>

    <div class="min-w-0 space-y-6 lg:col-start-1">
        <section class="card" aria-labelledby="desc-title">
            <div class="card-header">
                <h2 id="desc-title" class="card-title">Description</h2>
                <span class="text-sm text-muted"><?= e($t['requester_name']) ?> · <?= e(fmt_relative($t['created_at'])) ?></span>
            </div>
            <div class="card-body">
                <p class="whitespace-pre-line break-words text-ink"><?= e($t['description']) ?></p>
                <?php if ($ticketFiles): ?>
                    <?= View::partial('pages/tickets/_files', ['files' => $ticketFiles, 'class' => 'mt-5']) ?>
                <?php endif; ?>
            </div>
        </section>

        <?= View::partial('pages/tickets/_timeline', compact('t', 'comments', 'history', 'commentFiles')) ?>

        <?php if ($canComment): ?>
            <?= View::partial('pages/tickets/_reply', compact('t', 'canInternal', 'isRequester')) ?>
        <?php elseif ($t['status'] === 'closed'): ?>
            <p class="rounded-lg bg-neutral-tint px-4 py-3 text-sm text-neutral-text">
                This ticket is closed. <?= $isRequester ? 'If the problem comes back, please report a new problem.' : '' ?>
            </p>
        <?php endif; ?>
    </div>
</div>
