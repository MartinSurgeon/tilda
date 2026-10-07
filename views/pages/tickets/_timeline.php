<?php
/** @var array $t @var array $comments @var array $history @var array $commentFiles */
use App\Services\TicketMeta;

// Merge replies and status changes into one chronological activity feed.
$items = [];
foreach ($comments as $c) {
    $items[] = ['type' => 'comment', 'at' => $c['created_at'], 'order' => 1, 'id' => (int) $c['id'], 'row' => $c];
}
foreach ($history as $h) {
    if ($h['from_status'] === null) {
        continue; // creation is shown as the description
    }
    $items[] = ['type' => 'status', 'at' => $h['created_at'], 'order' => 0, 'id' => (int) $h['id'], 'row' => $h];
}
usort($items, static fn ($a, $b) => [$a['at'], $a['order'], $a['id']] <=> [$b['at'], $b['order'], $b['id']]);
$staffRoles = ['technician', 'it_manager'];
?>
<section aria-labelledby="activity-title">
    <h2 id="activity-title" class="section-title mb-3">Activity</h2>

    <?php if (!$items): ?>
        <p class="card card-body text-sm text-muted">No replies yet. Updates from IT will appear here.</p>
    <?php else: ?>
        <ol class="space-y-4" role="list">
            <?php foreach ($items as $item): $r = $item['row']; ?>
                <?php if ($item['type'] === 'status'): ?>
                    <li class="flex gap-3 rounded-lg border border-line bg-surface/70 p-3">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-smoke text-muted"><?= icon(TicketMeta::STATUSES[$r['to_status']]['icon'] ?? 'circle-dot', 'h-4 w-4') ?></span>
                        <div class="min-w-0 text-sm">
                            <p><span class="font-semibold text-ink"><?= e($r['full_name']) ?></span> changed the status to <?= TicketMeta::statusBadge($r['to_status']) ?>
                                <span class="text-muted">· <time datetime="<?= e(date('c', strtotime($r['created_at']))) ?>" title="<?= e(fmt_date($r['created_at'])) ?>"><?= e(fmt_relative($r['created_at'])) ?></time></span></p>
                            <?php if ($r['note'] !== ''): ?>
                                <p class="mt-1 whitespace-pre-line break-words border-l-2 border-line-strong pl-3 text-charcoal"><?= e($r['note']) ?></p>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php else: $internal = (int) $r['is_internal'] === 1; $staff = in_array($r['role'], $staffRoles, true); ?>
                    <li class="card <?= $internal ? 'border-warning/40 bg-warning-tint' : '' ?>">
                        <div class="flex items-start gap-3 p-4">
                            <span class="avatar <?= $staff ? '' : 'bg-midnight-tint text-midnight' ?>" aria-hidden="true"><?= e(initials($r['full_name'])) ?></span>
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                    <span class="font-semibold text-ink"><?= e($r['full_name']) ?></span>
                                    <?php if ($staff): ?><span class="badge-teal">IT</span><?php endif; ?>
                                    <?php if ($internal): ?><span class="badge-warning"><?= icon('lock', 'h-3.5 w-3.5') ?>Internal note, only IT can see this</span><?php endif; ?>
                                    <span class="text-muted"><time datetime="<?= e(date('c', strtotime($r['created_at']))) ?>" title="<?= e(fmt_date($r['created_at'])) ?>"><?= e(fmt_relative($r['created_at'])) ?></time></span>
                                </p>
                                <p class="mt-2 whitespace-pre-line break-words text-ink"><?= e($r['body']) ?></p>
                                <?php if (!empty($commentFiles[(int) $r['id']])): ?>
                                    <?= App\Core\View::partial('pages/tickets/_files', ['files' => $commentFiles[(int) $r['id']], 'class' => 'mt-3']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <span id="activity-end"></span>
</section>
