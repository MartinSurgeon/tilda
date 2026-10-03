<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Gate;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Ticket;
use App\Services\TicketMeta;
use App\Services\TicketPolicy;
use App\Services\TicketService;
use App\Services\Uploads;

/** State-changing actions on a single ticket. Each one re-checks TicketPolicy. */
final class TicketActionController
{
    public function accept(int $id): void
    {
        [$t, $user] = $this->load($id);
        if (!TicketPolicy::canAccept($t, $user)) {
            Gate::deny('ticket.accept');
        }
        $this->attempt($id, fn () => TicketService::assign($t, (int) $user['id'], $user), 'You have taken this ticket.');
    }

    public function assign(int $id): void
    {
        [$t, $user] = $this->load($id);
        if (!TicketPolicy::canAssign($t)) {
            Gate::deny('ticket.assign');
        }
        $assignee = (int) Request::input('assignee_id', 0);
        if ($assignee <= 0) {
            $this->fail($id, 'Please choose who should handle this ticket.');
        }
        $this->attempt($id, fn () => TicketService::assign($t, $assignee, $user), 'Ticket assigned.');
    }

    public function release(int $id): void
    {
        [$t, $user] = $this->load($id);
        if (!TicketPolicy::canRelease($t, $user)) {
            Gate::deny('ticket.release');
        }
        $this->attempt($id, fn () => TicketService::release($t, $user), 'The ticket is back in the queue.');
    }

    public function status(int $id): void
    {
        [$t, $user] = $this->load($id);
        $to = (string) Request::input('to', '');
        $note = mb_substr((string) Request::input('note', ''), 0, 500);

        if (!isset(TicketPolicy::transitions($t, $user)[$to])) {
            Gate::deny('ticket.status:' . $to);
        }
        $messages = [
            'closed'      => TicketPolicy::isRequester($t, $user) && $t['status'] === 'resolved'
                ? 'Thank you for confirming. The ticket is closed.' : 'Ticket closed.',
            'reopened'    => 'Ticket reopened. IT has been told.',
            'resolved'    => 'Marked as resolved. The requester will be asked to confirm.',
            'on_hold'     => 'Ticket is on hold. The SLA clock is paused.',
            'in_progress' => 'Ticket is now in progress.',
        ];
        $this->attempt($id, fn () => TicketService::transition($t, $to, $note, $user),
            $messages[$to] ?? 'Status updated to ' . TicketMeta::STATUSES[$to]['label'] . '.');
    }

    public function priority(int $id): void
    {
        [$t, $user] = $this->load($id);
        if (!TicketPolicy::canChangePriority($t, $user)) {
            Gate::deny('ticket.priority');
        }
        $priorityId = (int) Request::input('priority_id', 0);
        $reason = mb_substr((string) Request::input('reason', ''), 0, 300);
        if ($reason === '') {
            $this->fail($id, 'Please say why the priority is changing.');
        }
        if (!\App\Core\DB::value('SELECT 1 FROM priorities WHERE id = ?', [$priorityId])) {
            $this->fail($id, 'Please choose a priority.');
        }
        $this->attempt($id, fn () => TicketService::changePriority($t, $priorityId, $reason, $user),
            'Priority updated. SLA targets were recalculated.');
    }

    public function comment(int $id): void
    {
        [$t, $user] = $this->load($id);
        if (!TicketPolicy::canComment($t, $user)) {
            Gate::deny('ticket.comment');
        }
        $body = (string) Request::input('body', '');
        $internal = Request::input('is_internal') === '1';
        if ($internal && !TicketPolicy::canSeeInternal()) {
            Gate::deny('ticket.internal_note');
        }

        $errors = [];
        if (mb_strlen(trim($body)) < 2) {
            $errors['body'] = 'Please write a message before sending.';
        } elseif (mb_strlen($body) > 5000) {
            $errors['body'] = 'Messages can be at most 5,000 characters.';
        }
        $files = Uploads::fromRequest('attachments');
        if (count($files) > Uploads::MAX_FILES) {
            $errors['attachments'] = 'You can attach up to ' . Uploads::MAX_FILES . ' files.';
        }
        foreach ($files as $file) {
            if (!isset($errors['attachments']) && ($msg = Uploads::validate($file))) {
                $errors['attachments'] = $msg;
            }
        }
        if ($errors) {
            if ($files && !isset($errors['attachments'])) {
                $errors['attachments'] = 'Please attach your files again.';
            }
            back_with_errors($errors, "/tickets/{$id}#reply", ['body' => $body, 'is_internal' => $internal ? '1' : '']);
        }

        $this->attempt($id, fn () => TicketService::comment($t, $body, $internal, $user, $files),
            $internal ? 'Internal note added.' : 'Reply sent.', '#activity-end');
    }

    // ------------------------------------------------------------------

    /** @return array{0: array, 1: array} ticket and user; 404 if the ticket is not visible */
    private function load(int $id): array
    {
        $user = Auth::user();
        $t = Ticket::find($id);
        if (!$t || !TicketPolicy::canView($t, $user)) {
            throw new HttpException(404);
        }
        return [$t, $user];
    }

    private function attempt(int $id, callable $action, string $success, string $anchor = ''): never
    {
        try {
            $action();
        } catch (HttpException $e) {
            if (in_array($e->status, [409, 422], true)) {
                $this->fail($id, $e->getMessage());
            }
            throw $e;
        }
        Session::flash('success', $success);
        Response::redirect("/tickets/{$id}{$anchor}");
    }

    private function fail(int $id, string $message): never
    {
        Session::flash('danger', $message);
        Response::redirect("/tickets/{$id}");
    }
}
