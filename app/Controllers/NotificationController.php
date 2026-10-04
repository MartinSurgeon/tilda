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
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\LiveVersion;
use App\Services\Notifier;

final class NotificationController
{
    private const PER_PAGE = 25;

    public function index(): void
    {
        $uid = (int) Auth::id();
        $unreadOnly = Request::query('show') === 'unread';
        $page = max(1, (int) Request::query('page', 1));
        $where = 'user_id = ?' . ($unreadOnly ? ' AND read_at IS NULL' : '');

        $total = (int) DB::value("SELECT COUNT(*) FROM notifications WHERE {$where}", [$uid]);
        $rows = DB::all("SELECT * FROM notifications WHERE {$where} ORDER BY id DESC LIMIT ? OFFSET ?",
            [$uid, self::PER_PAGE, ($page - 1) * self::PER_PAGE]);

        View::show('notifications/index', [
            'title'         => 'Notifications',
            'notifications' => $rows,
            'unreadOnly'    => $unreadOnly,
            'unread'        => unread_notifications(),
            'page'          => $page,
            'pages'         => max(1, (int) ceil($total / self::PER_PAGE)),
        ]);
    }

    /** Open = mark read, then go to the target. Only same-site paths are followed. */
    public function open(int $id): void
    {
        $n = DB::one('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [$id, (int) Auth::id()]);
        if (!$n) {
            throw new HttpException(404);
        }
        DB::run('UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE id = ?', [$id]);
        $url = (string) $n['url'];
        Response::redirect(safe_path($url, '/notifications'));
    }

    public function readAll(): void
    {
        $n = DB::run('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [(int) Auth::id()])->rowCount();
        Session::flash('success', $n ? 'All notifications marked as read.' : 'You are all caught up.');
        Response::redirect('/notifications');
    }

    public function preferences(): void
    {
        $user = Auth::user();
        $events = Notifier::eventsFor(Gate::allows('ticket.work'));
        $before = array_column(DB::all('SELECT event, email FROM notification_preferences WHERE user_id = ?', [$user['id']]), 'email', 'event');
        $chosen = (array) ($_POST['email'] ?? []);

        $changes = [];
        foreach ($events as $event => [, $default]) {
            $on = isset($chosen[$event]) ? 1 : 0;
            DB::run('INSERT INTO notification_preferences (user_id, event, in_app, email) VALUES (?, ?, 1, ?)
                     ON DUPLICATE KEY UPDATE email = VALUES(email)', [$user['id'], $event, $on]);
            $old = isset($before[$event]) ? (int) $before[$event] : ($default ? 1 : 0);
            if ($old !== $on) {
                $changes[$event] = ['from' => $old, 'to' => $on];
            }
        }
        if ($changes) {
            AuditLogger::log('account.notification_preferences', 'user', $user['id'], 'Changed email notification settings', ['changes' => $changes]);
        }
        Session::flash('success', 'Your email settings are saved.');
        Response::redirect('/account');
    }

    /**
     * Polled every ~15 s by the browser. Sent with X-Background, so it never
     * extends the idle timeout. Read-only and cheap.
     */
    public function poll(): void
    {
        $user = Auth::user();
        $latest = DB::one('SELECT id, title, url FROM notifications WHERE user_id = ? AND read_at IS NULL ORDER BY id DESC LIMIT 1',
            [(int) $user['id']]);
        Response::json([
            'unread'  => unread_notifications(),
            'version' => LiveVersion::for($user),
            'latest'  => $latest ? ['id' => (int) $latest['id'], 'title' => $latest['title']] : null,
        ]);
    }
}
