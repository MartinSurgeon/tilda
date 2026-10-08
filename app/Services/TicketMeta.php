<?php
declare(strict_types=1);

namespace App\Services;

/** Display metadata for statuses and priority tones. Colour is always paired with an icon and text. */
final class TicketMeta
{
    public const STATUSES = [
        'open'        => ['label' => 'Open',        'badge' => 'badge-info',     'icon' => 'circle-dot'],
        'assigned'    => ['label' => 'Assigned',    'badge' => 'badge-midnight', 'icon' => 'user-check'],
        'in_progress' => ['label' => 'In progress', 'badge' => 'badge-teal',     'icon' => 'play-circle'],
        'on_hold'     => ['label' => 'On hold',     'badge' => 'badge-warning',  'icon' => 'pause-circle'],
        'resolved'    => ['label' => 'Resolved',    'badge' => 'badge-success',  'icon' => 'check-circle'],
        'closed'      => ['label' => 'Closed',      'badge' => 'badge-neutral',  'icon' => 'archive'],
        'reopened'    => ['label' => 'Reopened',    'badge' => 'badge-danger',   'icon' => 'rotate-ccw'],
    ];

    public const TONES = [
        'critical' => 'badge-danger',
        'high'     => 'badge-warning',
        'medium'   => 'badge-midnight',
        'low'      => 'badge-neutral',
    ];

    public static function statusBadge(string $status): string
    {
        $meta = self::STATUSES[$status] ?? ['label' => ucfirst($status), 'badge' => 'badge-neutral', 'icon' => 'help'];
        return '<span class="' . $meta['badge'] . '">' . icon($meta['icon'], 'h-3.5 w-3.5') . e($meta['label']) . '</span>';
    }

    public static function slaBadge(array $t): string
    {
        $s = Sla::state($t);
        if ($s['state'] === 'none') {
            return '';
        }
        return '<span class="' . $s['badge'] . '">' . icon($s['icon'], 'h-3.5 w-3.5') . e($s['label']) . '</span>';
    }

    /** Deadline chip only when it needs attention (overdue or close). On-track tickets stay quiet. */
    public static function deadlineAlert(array $t): string
    {
        return in_array(Sla::state($t)['state'], ['breached', 'at_risk'], true) ? self::slaBadge($t) : '';
    }

    /** Priority chip only for Critical and High; Medium and Low are the normal case. */
    public static function priorityAlert(array $t): string
    {
        return in_array($t['tone'], ['critical', 'high'], true)
            ? self::priorityBadge($t['priority_name'], $t['tone'], $t['priority_icon'])
            : '';
    }

    public static function priorityBadge(string $name, string $tone, string $icon): string
    {
        $class = self::TONES[$tone] ?? 'badge-neutral';
        return '<span class="' . $class . '">' . icon($icon, 'h-3.5 w-3.5') . e($name) . '</span>';
    }
}
