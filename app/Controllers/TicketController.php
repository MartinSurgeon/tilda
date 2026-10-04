<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Gate;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Lookup;
use App\Models\Ticket;
use App\Services\AuditLogger;
use App\Services\TicketMeta;
use App\Services\TicketPolicy;
use App\Services\TicketService;
use App\Services\Uploads;

final class TicketController
{
    private const PER_PAGE = 20;

    public function create(): void
    {
        View::show('tickets/create', [
            'title'         => 'Report a problem',
            'categories'    => Lookup::categories(),
            'subcategories' => Lookup::subcategories(),
            'departments'   => Lookup::departments(),
            'priorities'    => Lookup::priorities(),
        ]);
    }

    public function store(): void
    {
        $user = Auth::user();
        $data = Request::only(['category_id', 'subcategory_id', 'title', 'description', 'priority_id', 'department_id', 'location']);
        $data['title'] = one_line($data['title']);
        $data['location'] = one_line($data['location']);

        $v = Validator::make($data, [
            'category_id'   => 'required|int',
            'title'         => 'required|min:5|max:150',
            'description'   => 'required|min:10|max:5000',
            'priority_id'   => 'required|int|exists:priorities,id',
            'department_id' => 'required|int',
            'location'      => 'max:120',
            'subcategory_id'=> 'int',
        ], [
            'category_id' => 'what the problem is about', 'title' => 'Short summary', 'description' => 'Description',
            'priority_id' => 'how urgent it is', 'department_id' => 'department', 'location' => 'Location',
            'subcategory_id' => 'type of problem',
        ]);

        // Messages tuned for this form (the generic ones read oddly here).
        if (($data['category_id'] ?? '') === '') {
            $v->addError('category_id', 'Please choose what the problem is about.');
        } elseif (!DB::value('SELECT 1 FROM categories WHERE id = ? AND parent_id IS NULL AND is_active = 1', [(int) $data['category_id']])) {
            $v->addError('category_id', 'Please choose what the problem is about.');
        }
        if ($data['subcategory_id'] !== '' && !DB::value(
            'SELECT 1 FROM categories WHERE id = ? AND parent_id = ? AND is_active = 1',
            [(int) $data['subcategory_id'], (int) $data['category_id']]
        )) {
            $v->addError('subcategory_id', 'Please choose a type that matches the category, or leave it blank.');
        }
        if (!DB::value('SELECT 1 FROM departments WHERE id = ? AND is_active = 1', [(int) $data['department_id']])) {
            $v->addError('department_id', 'Please choose your department.');
        }

        // Flood protection: a real person rarely reports more than a few problems an hour.
        $recent = (int) DB::value('SELECT COUNT(*) FROM tickets WHERE requester_id = ? AND created_at > NOW() - INTERVAL 1 HOUR', [$user['id']]);
        if ($recent >= 20) {
            AuditLogger::log('security.rate_limited', 'ticket', null, 'Ticket creation limit reached', ['last_hour' => $recent]);
            $v->addError('title', 'You have reported a lot of problems in the last hour. Please wait a little, or phone the IT help desk if it is urgent.');
        }

        $files = Uploads::fromRequest('attachments');
        if (count($files) > Uploads::MAX_FILES) {
            $v->addError('attachments', 'You can attach up to ' . Uploads::MAX_FILES . ' files.');
        }
        foreach ($files as $file) {
            if ($msg = Uploads::validate($file)) {
                $v->addError('attachments', $msg);
                break;
            }
        }

        if ($v->fails()) {
            $errors = $v->errors();
            if ($files && !isset($errors['attachments'])) {
                // Browsers cannot re-fill file inputs; say so instead of silently dropping them.
                $errors['attachments'] = 'Please attach your files again.';
            }
            back_with_errors($errors, '/tickets/create', $data);
        }

        $ticket = TicketService::create([
            'title'          => $data['title'],
            'description'    => $data['description'],
            'category_id'    => (int) $data['category_id'],
            'subcategory_id' => $data['subcategory_id'] !== '' ? (int) $data['subcategory_id'] : null,
            'department_id'  => (int) $data['department_id'],
            'location'       => $data['location'],
            'priority_id'    => (int) $data['priority_id'],
        ], $user, $files);

        Response::redirect('/tickets/' . $ticket['id'] . '/submitted');
    }

    /** Confirmation after submitting (peak-end: clear outcome and next step). */
    public function submitted(int $id): void
    {
        $t = $this->findVisible($id);
        if (!TicketPolicy::isRequester($t, Auth::user())) {
            Response::redirect('/tickets/' . $id);
        }
        $sla = DB::one('SELECT response_minutes FROM sla_policies WHERE priority_id = ?', [(int) $t['priority_id']]);
        View::show('tickets/submitted', ['title' => 'Ticket sent', 't' => $t, 'responseMinutes' => (int) ($sla['response_minutes'] ?? 0)]);
    }

    /** IT queue. */
    public function index(): void
    {
        $user = Auth::user();
        $counts = Ticket::queueCounts((int) $user['id']);
        $views = ['mine' => 'Assigned to me', 'unassigned' => 'Unassigned', 'active' => 'All open', 'overdue' => 'Overdue', 'done' => 'Resolved & closed'];

        $default = ((int) ($counts['mine'] ?? 0) > 0 || !Gate::allows('ticket.assign')) ? 'mine' : 'unassigned';
        $view = (string) Request::query('view', $default);
        if (!isset($views[$view]) && $view !== 'all') {
            $view = $default;
        }

        $filters = $this->filters();
        $page = max(1, (int) Request::query('page', 1));
        $result = Ticket::search(['view' => $view] + $filters, (int) $user['id'], $page, self::PER_PAGE);

        View::show('tickets/index', [
            'title'       => 'Ticket queue',
            'view'        => $view,
            'views'       => $views,
            'counts'      => $counts,
            'filters'     => $filters,
            'tickets'     => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page,
            'pages'       => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
            'categories'  => Lookup::categories(false),
            'departments' => Lookup::departments(false),
            'priorities'  => Lookup::priorities(),
            'technicians' => Lookup::technicians(),
        ]);
    }

    /** Tickets the signed-in user raised. */
    public function mine(): void
    {
        $user = Auth::user();
        $view = Request::query('view') === 'done' ? 'done' : 'active';
        $page = max(1, (int) Request::query('page', 1));
        $result = Ticket::search(['view' => $view, 'requester' => (int) $user['id'], 'sort' => 'updated'], (int) $user['id'], $page, self::PER_PAGE);
        $counts = DB::one(
            'SELECT SUM(status IN ' . Ticket::ACTIVE . ") AS active, SUM(status IN ('resolved','closed')) AS done,
                    SUM(status = 'resolved') AS awaiting
             FROM tickets WHERE requester_id = ?",
            [(int) $user['id']]
        );

        View::show('tickets/mine', [
            'title'   => 'My tickets',
            'view'    => $view,
            'counts'  => $counts,
            'tickets' => $result['rows'],
            'page'    => $page,
            'pages'   => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
        ]);
    }

    public function show(int $id): void
    {
        $user = Auth::user();
        $t = $this->findVisible($id);
        $internal = TicketPolicy::canSeeInternal();

        View::show('tickets/show', [
            'title'       => $t['ref'],
            't'           => $t,
            'comments'    => Ticket::comments($id, $internal),
            'history'     => Ticket::history($id),
            'attachments' => Ticket::attachments($id, $internal),
            'transitions' => TicketPolicy::transitions($t, $user),
            'canComment'  => TicketPolicy::canComment($t, $user),
            'canInternal' => $internal,
            'canAccept'   => TicketPolicy::canAccept($t, $user),
            'canAssign'   => TicketPolicy::canAssign($t),
            'canRelease'  => TicketPolicy::canRelease($t, $user),
            'canPriority' => TicketPolicy::canChangePriority($t, $user),
            'technicians' => TicketPolicy::canAssign($t) ? Lookup::technicians() : [],
            'priorities'  => Lookup::priorities(),
        ]);
    }

    // ------------------------------------------------------------------

    /** Loads the ticket and enforces view permission (404 rather than 403, so refs are not probeable). */
    private function findVisible(int $id): array
    {
        $t = Ticket::find($id);
        if (!$t || !TicketPolicy::canView($t, Auth::user())) {
            if ($t) {
                AuditLogger::log('access.denied', 'ticket', $id, 'Tried to open a ticket without permission', [
                    'path' => Request::method() . ' ' . Request::path(),
                ]);
            }
            throw new HttpException(404);
        }
        return $t;
    }

    private function filters(): array
    {
        $status = (string) Request::query('status', '');
        return [
            'q'          => mb_substr((string) Request::query('q', ''), 0, 100),
            'status'     => isset(TicketMeta::STATUSES[$status]) ? $status : '',
            'priority'   => (int) Request::query('priority', 0),
            'category'   => (int) Request::query('category', 0),
            'department' => (int) Request::query('department', 0),
            'assignee'   => Request::query('assignee') === 'none' ? 'none' : (int) Request::query('assignee', 0),
        ];
    }
}
