<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * SLA targets come from sla_policies (per priority). Time spent On hold is
 * excluded: due dates move back by the hold duration (tickets.hold_minutes).
 */
final class Sla
{
    private static array $policies = [];

    public static function policy(int $priorityId): array
    {
        return self::$policies[$priorityId] ??= DB::one(
            'SELECT response_minutes, resolution_minutes, warn_percent FROM sla_policies WHERE priority_id = ?',
            [$priorityId]
        ) ?? ['response_minutes' => 0, 'resolution_minutes' => 0, 'warn_percent' => 80];
    }

    /** @return array{response_due_at: string, resolve_due_at: string} */
    public static function dueDates(int $priorityId, string $createdAt, int $holdMinutes = 0): array
    {
        $p = self::policy($priorityId);
        $base = strtotime($createdAt);
        return [
            'response_due_at' => date('Y-m-d H:i:s', $base + $p['response_minutes'] * 60),
            'resolve_due_at'  => date('Y-m-d H:i:s', $base + ($p['resolution_minutes'] + $holdMinutes) * 60),
        ];
    }

    /**
     * Resolution SLA state for display.
     * @return array{state: string, label: string, badge: string, icon: string}
     *   state: met | breached | paused | at_risk | on_track | none
     */
    public static function state(array $t): array
    {
        if (empty($t['resolve_due_at'])) {
            return self::out('none', 'No target', 'badge-outline-neutral', 'clock');
        }
        $due = strtotime($t['resolve_due_at']);

        if (in_array($t['status'], ['resolved', 'closed'], true)) {
            $done = strtotime($t['resolved_at'] ?? $t['closed_at'] ?? 'now');
            return $done <= $due
                ? self::out('met', 'Within target', 'badge-outline-success', 'check-circle')
                : self::out('breached', 'Missed target', 'badge-outline-danger', 'alert-triangle');
        }
        // The deadline moves back when the hold ends, so "overdue" would be premature.
        if ($t['status'] === 'on_hold') {
            return self::out('paused', 'Clock paused', 'badge-outline-neutral', 'pause-circle');
        }
        if (time() > $due) {
            return self::out('breached', 'Overdue by ' . self::duration(time() - $due), 'badge-outline-danger', 'alert-triangle');
        }

        $start = strtotime($t['created_at']);
        $total = max(1, $due - $start);
        $warn = (int) ($t['warn_percent'] ?? self::policy((int) $t['priority_id'])['warn_percent']);
        $left = 'Due in ' . self::duration($due - time());
        return (time() - $start) / $total * 100 >= $warn
            ? self::out('at_risk', $left, 'badge-outline-warning', 'clock')
            : self::out('on_track', $left, 'badge-outline-teal', 'clock');
    }

    /** "45 min", "3 h 10 min", "2 d 4 h" */
    public static function duration(int $seconds): string
    {
        $m = intdiv(max(0, $seconds), 60);
        if ($m < 60) {
            return max(1, $m) . ' min';
        }
        $h = intdiv($m, 60);
        if ($h < 24) {
            return $h . ' h' . ($m % 60 ? ' ' . ($m % 60) . ' min' : '');
        }
        $d = intdiv($h, 24);
        return $d . ' d' . ($h % 24 ? ' ' . ($h % 24) . ' h' : '');
    }

    /** Minutes as plain words for staff: "15 minutes", "4 hours", "3 days". */
    public static function humanMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
        }
        if ($minutes < 1440 || $minutes % 1440 !== 0) {
            $h = round($minutes / 60, 1);
            return rtrim(rtrim(number_format($h, 1), '0'), '.') . ' hour' . ($h == 1 ? '' : 's');
        }
        $d = intdiv($minutes, 1440);
        return $d . ' day' . ($d === 1 ? '' : 's');
    }

    private static function out(string $state, string $label, string $badge, string $icon): array
    {
        return compact('state', 'label', 'badge', 'icon');
    }
}
