<?php
/**
 * Opened vs resolved per day. JS draws the SVG at the container's real width
 * (so text never scales); the legend and the table are server-rendered, so
 * every value is reachable without the chart or hover.
 * @var array<string, array{opened: int, resolved: int}> $trend
 */
$labels = array_map(static fn ($d) => date('j M', strtotime($d)), array_keys($trend));
$opened = array_column($trend, 'opened');
$resolved = array_column($trend, 'resolved');
$chart = [
    'labels' => $labels,
    'series' => [
        ['name' => 'Opened', 'key' => 'opened', 'values' => $opened],
        ['name' => 'Resolved', 'key' => 'resolved', 'values' => $resolved],
    ],
];
$sumO = array_sum($opened);
$sumR = array_sum($resolved);
?>
<div class="mb-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-sm">
    <span class="inline-flex items-center gap-2"><span class="legend-line legend-opened" aria-hidden="true"></span>Opened <strong class="text-ink"><?= $sumO ?></strong></span>
    <span class="inline-flex items-center gap-2"><span class="legend-line legend-resolved" aria-hidden="true"></span>Resolved <strong class="text-ink"><?= $sumR ?></strong></span>
</div>

<div class="trend-chart relative" data-chart="<?= e(json_encode($chart)) ?>" tabindex="0" role="img"
     aria-label="Line chart, last 14 days: <?= $sumO ?> tickets opened and <?= $sumR ?> resolved. Use left and right arrow keys to read each day.">
    <noscript><p class="text-sm text-muted">Chart needs JavaScript. See the table below.</p></noscript>
</div>

<details class="mt-3">
    <summary class="inline-flex min-h-touch cursor-pointer items-center text-sm font-semibold text-midnight">Show as table</summary>
    <div class="mt-2 overflow-x-auto" tabindex="0" role="region" aria-label="Daily figures">
        <table class="table">
            <thead><tr><th scope="col">Day</th><th scope="col" class="text-right">Opened</th><th scope="col" class="text-right">Resolved</th></tr></thead>
            <tbody>
            <?php foreach ($labels as $i => $label): ?>
                <tr><th scope="row" class="font-normal"><?= e($label) ?></th><td class="text-right"><?= (int) $opened[$i] ?></td><td class="text-right"><?= (int) $resolved[$i] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</details>
