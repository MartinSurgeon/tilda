<?php /* Keyboard shortcut reference, opened with "?" or from the account menu. */ ?>
<dialog id="shortcuts-help" class="w-[calc(100%-2rem)] max-w-lg rounded-xl bg-surface p-0 text-charcoal backdrop:bg-black/50" aria-labelledby="shortcuts-title">
    <div class="flex items-center justify-between border-b border-line px-5 py-3">
        <h2 id="shortcuts-title" class="text-base font-semibold">Keyboard shortcuts</h2>
        <button type="button" class="btn-icon" data-close-dialog aria-label="Close"><?= icon('x') ?></button>
    </div>
    <div class="max-h-[70vh] space-y-5 overflow-y-auto px-5 py-4 text-sm">
        <p>Shortcuts never work while you are typing in a box. They are <strong data-shortcuts-state>on</strong> in this browser.</p>
        <?php foreach ([
            'Anywhere' => [['?', 'Show this list'], ['g then d', 'Dashboard'], ['g then q', 'Ticket queue'], ['g then m', 'My tickets'], ['g then n', 'Notifications'], ['n', 'Report a new problem'], ['/', 'Search (on pages with a search box)']],
            'In a list of tickets' => [['j / k', 'Next / previous ticket'], ['Enter', 'Open the selected ticket'], ['t', 'Take the selected ticket (if unassigned)']],
            'On a ticket' => [['r', 'Write a reply or note'], ['t', 'Take this ticket (if unassigned)']],
        ] as $group => $rows): ?>
            <section>
                <h3 class="section-title mb-2"><?= e($group) ?></h3>
                <dl class="space-y-1.5">
                    <?php foreach ($rows as [$keys, $what]): ?>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="flex gap-1"><?php foreach (preg_split('/\s+(then|\/)\s+/', $keys) as $i => $k): ?><?= $i ? '<span class="text-muted">' . (str_contains($keys, '/') ? '/' : 'then') . '</span>' : '' ?><kbd class="kbd"><?= e($k) ?></kbd><?php endforeach; ?></dt>
                            <dd class="text-right text-ink"><?= e($what) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endforeach; ?>
    </div>
    <div class="flex justify-end border-t border-line px-5 py-3">
        <button type="button" class="btn-secondary" data-shortcuts-toggle>Turn shortcuts off</button>
    </div>
</dialog>
