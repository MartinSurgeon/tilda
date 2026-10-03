<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Gate;

/**
 * Object-level authorization and the status workflow, in one place.
 * Controllers ask this class; views only use it to decide what to show.
 *
 *   Open → Assigned → In progress ⇄ On hold → Resolved → Closed
 *                                               ↘ Reopened → (back into work)
 */
final class TicketPolicy
{
    /** IT-side transitions (ticket.work), from => [to, ...]. Open/Reopened leave via assignment. */
    private const IT_FLOW = [
        'open'        => ['closed'],
        'assigned'    => ['in_progress', 'on_hold', 'resolved'],
        'in_progress' => ['on_hold', 'resolved'],
        'on_hold'     => ['in_progress', 'resolved'],
        'reopened'    => ['in_progress', 'on_hold', 'resolved'],
        'resolved'    => ['closed', 'reopened'],
        'closed'      => [],
    ];

    /** What each transition is called, and whether it needs an explanation. */
    public const ACTIONS = [
        'in_progress' => ['label' => 'Start work',      'icon' => 'play-circle',  'note' => false],
        'on_hold'     => ['label' => 'Put on hold',     'icon' => 'pause-circle', 'note' => true,
                          'prompt' => 'What are you waiting for?', 'hint' => 'For example: replacement part ordered, waiting for supplier. The requester will see this.'],
        'resolved'    => ['label' => 'Mark resolved',   'icon' => 'check-circle', 'note' => true,
                          'prompt' => 'What did you do to fix it?', 'hint' => 'Plain words. The requester will see this and confirm the fix.'],
        'closed'      => ['label' => 'Close ticket',    'icon' => 'archive',      'note' => true,
                          'prompt' => 'Why are you closing it?', 'hint' => 'For example: duplicate of another ticket, or no reply from requester.'],
        'reopened'    => ['label' => 'Reopen',          'icon' => 'rotate-ccw',   'note' => true,
                          'prompt' => 'What is still wrong?', 'hint' => 'Tell IT what happens now so they can pick it up quickly.'],
    ];

    public static function isRequester(array $t, array $user): bool
    {
        return (int) $t['requester_id'] === (int) $user['id'];
    }

    public static function isAssignee(array $t, array $user): bool
    {
        return $t['assignee_id'] !== null && (int) $t['assignee_id'] === (int) $user['id'];
    }

    public static function canView(array $t, array $user): bool
    {
        return Gate::allows('ticket.view_all')
            || (Gate::allows('ticket.view_own') && self::isRequester($t, $user));
    }

    public static function canComment(array $t, array $user): bool
    {
        if ($t['status'] === 'closed' || !self::canView($t, $user)) {
            return false;
        }
        return Gate::allows('ticket.work') || self::isRequester($t, $user);
    }

    public static function canSeeInternal(): bool
    {
        return Gate::allows('ticket.work');
    }

    public static function canAttach(array $t, array $user): bool
    {
        return self::canComment($t, $user);
    }

    /** A technician may only drive tickets that are theirs or unowned; managers (reassign) may drive any. */
    private static function canWorkOn(array $t, array $user): bool
    {
        if (!Gate::allows('ticket.work')) {
            return false;
        }
        return $t['assignee_id'] === null || self::isAssignee($t, $user) || Gate::allows('ticket.reassign');
    }

    /** "Accept": take an unowned ticket yourself. */
    public static function canAccept(array $t, array $user): bool
    {
        return Gate::allows('ticket.work') && $t['assignee_id'] === null
            && in_array($t['status'], ['open', 'reopened'], true);
    }

    /** Pick any technician: unowned needs ticket.assign, owned needs ticket.reassign. */
    public static function canAssign(array $t): bool
    {
        if (in_array($t['status'], ['resolved', 'closed'], true)) {
            return false;
        }
        return $t['assignee_id'] === null ? Gate::allows('ticket.assign') : Gate::allows('ticket.reassign');
    }

    /** The assignee can hand an unfinished ticket back to the queue. */
    public static function canRelease(array $t, array $user): bool
    {
        return self::isAssignee($t, $user) && !in_array($t['status'], ['resolved', 'closed'], true);
    }

    public static function canChangePriority(array $t, array $user): bool
    {
        return self::canWorkOn($t, $user) && !in_array($t['status'], ['resolved', 'closed'], true);
    }

    /**
     * Allowed target statuses for this user, with who-is-asking context.
     * @return array<string, array{label: string, icon: string, note: bool, as: string}>
     */
    public static function transitions(array $t, array $user): array
    {
        $out = [];
        $from = $t['status'];

        if (self::canWorkOn($t, $user)) {
            foreach (self::IT_FLOW[$from] ?? [] as $to) {
                // Work states need an owner first (Accept/Assign does that).
                if ($t['assignee_id'] === null && in_array($to, ['in_progress', 'on_hold', 'resolved'], true)) {
                    continue;
                }
                $out[$to] = self::ACTIONS[$to] + ['as' => 'it'];
            }
        }

        if (self::isRequester($t, $user) && Gate::allows('ticket.view_own')) {
            if ($from === 'resolved') {
                $out['closed'] = ['label' => 'Yes, it is fixed', 'icon' => 'check-circle', 'note' => false, 'as' => 'requester'];
                $out['reopened'] = self::ACTIONS['reopened'] + ['as' => 'requester'];
                $out['reopened']['label'] = 'No, still a problem';
            } elseif ($from !== 'closed' && !isset($out['closed'])) {
                $out['closed'] = ['label' => 'Cancel my request', 'icon' => 'x', 'note' => false, 'as' => 'requester',
                                  'hint' => 'Use this if you no longer need help.'];
            }
        }

        return $out;
    }
}
