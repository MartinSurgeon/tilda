<?php
/**
 * KPI tile with change vs the previous period. Change is spelled out in words
 * plus an arrow (never colour alone); colour only says whether it is good news.
 * @var string $label @var string $value @var ?float $now @var ?float $before
 * @var string $better  'up' | 'down' | 'none' — which direction is good
 * @var string $unit    shown after the difference (e.g. "pts", " min")
 * @var string $prevLabel
 */
$diff = ($now !== null && $before !== null) ? $now - $before : null;
$class = 'text-muted';
$text = 'No earlier data';
$arrow = 'minus';
if ($diff !== null) {
    if (abs($diff) < 0.5) {
        $text = 'Same as ' . $prevLabel;
    } else {
        $up = $diff > 0;
        $arrow = $up ? 'arrow-up' : 'arrow-down';
        $n = abs((int) round($diff));
        $shown = match ($unit) {
            ' min' => App\Services\ReportBuilder::minutes((int) abs($diff)),
            ' pts' => $n . ($n === 1 ? ' point' : ' points'),
            default => (string) $n,
        };
        $text = ($up ? 'Up ' : 'Down ') . $shown . ' on ' . $prevLabel;
        if ($better !== 'none') {
            $class = ($up === ($better === 'up')) ? 'text-green-text' : 'text-danger-fg';
        }
    }
}
?>
<div class="card p-4">
    <p class="text-sm text-muted"><?= e($label) ?></p>
    <p class="mt-1 text-2xl font-bold text-ink"><?= e($value) ?></p>
    <p class="mt-1 flex items-center gap-1 text-xs font-medium <?= $class ?>"><?= icon($arrow, 'h-3.5 w-3.5 shrink-0') ?><span><?= e($text) ?></span></p>
</div>
