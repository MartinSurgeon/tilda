<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\PasswordPolicy;

final class UserController
{
    private const PER_PAGE = 20;
    private const FIELDS = ['full_name', 'email', 'phone', 'job_title', 'role_id', 'department_id', 'is_active'];

    public function index(): void
    {
        $q = (string) Request::query('q', '');
        $role = (int) Request::query('role', 0);
        $status = (string) Request::query('status', '');
        $page = max(1, (int) Request::query('page', 1));

        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(u.full_name LIKE ? OR u.email LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like);
        }
        if ($role > 0) {
            $where[] = 'u.role_id = ?';
            $params[] = $role;
        }
        if ($status === 'active' || $status === 'inactive') {
            $where[] = 'u.is_active = ?';
            $params[] = $status === 'active' ? 1 : 0;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) DB::value("SELECT COUNT(*) FROM users u {$whereSql}", $params);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        $users = DB::all(
            "SELECT u.id, u.full_name, u.email, u.job_title, u.is_active, u.locked_until, u.last_login_at,
                    u.must_change_password, r.name AS role_name, r.slug AS role, d.name AS department_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN departments d ON d.id = u.department_id
             {$whereSql}
             ORDER BY u.is_active DESC, u.full_name
             LIMIT ? OFFSET ?",
            [...$params, self::PER_PAGE, ($page - 1) * self::PER_PAGE]
        );

        View::show('admin/users/index', [
            'title'   => 'Users',
            'users'   => $users,
            'roles'   => $this->roles(),
            'filters' => compact('q', 'role', 'status'),
            'page'    => $page,
            'pages'   => $pages,
            'total'   => $total,
        ]);
    }

    public function create(): void
    {
        View::show('admin/users/form', [
            'title'       => 'Add user',
            'user'        => null,
            'roles'       => $this->roles(),
            'departments' => $this->departments(),
        ]);
    }

    public function store(): void
    {
        $data = $this->validated('/admin/users/create');

        $temporary = $this->temporaryPassword();
        $id = DB::insert(
            'INSERT INTO users (full_name, email, phone, job_title, role_id, department_id, is_active,
                                password_hash, must_change_password)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)',
            [
                $data['full_name'], $data['email'], $data['phone'] ?: null, $data['job_title'] ?: null,
                (int) $data['role_id'], $data['department_id'] ? (int) $data['department_id'] : null,
                (int) $data['is_active'], password_hash($temporary, PASSWORD_DEFAULT, ['cost' => 12]),
            ]
        );

        AuditLogger::log('user.created', 'user', $id, "Created user {$data['email']}", [
            'role_id'       => (int) $data['role_id'],
            'department_id' => $data['department_id'] ? (int) $data['department_id'] : null,
        ]);

        // Shown once, never stored in plain text or logged.
        Session::set('_temp_password', ['user' => $data['full_name'], 'password' => $temporary]);
        Session::flash('success', "{$data['full_name']} has been added.");
        Response::redirect('/admin/users');
    }

    public function edit(int $id): void
    {
        View::show('admin/users/form', [
            'title'       => 'Edit user',
            'user'        => $this->find($id),
            'roles'       => $this->roles(),
            'departments' => $this->departments(),
        ]);
    }

    public function update(int $id): void
    {
        $before = $this->find($id);
        $data = $this->validated("/admin/users/{$id}/edit", $id);

        // Guard against an admin locking everyone (including themselves) out.
        if ($id === Auth::id() && ((int) $data['role_id'] !== (int) $before['role_id'] || (int) $data['is_active'] === 0)) {
            back_with_errors(['role_id' => 'You cannot change your own role or deactivate yourself. Ask another administrator.'],
                "/admin/users/{$id}/edit", $data);
        }

        DB::run(
            'UPDATE users SET full_name = ?, email = ?, phone = ?, job_title = ?, role_id = ?, department_id = ?, is_active = ?
             WHERE id = ?',
            [
                $data['full_name'], $data['email'], $data['phone'] ?: null, $data['job_title'] ?: null,
                (int) $data['role_id'], $data['department_id'] ? (int) $data['department_id'] : null,
                (int) $data['is_active'], $id,
            ]
        );

        $changes = [];
        foreach (self::FIELDS as $field) {
            $old = $before[$field] === null ? '' : (string) $before[$field];
            $new = (string) $data[$field];
            if ($old !== $new) {
                $changes[$field] = ['from' => $before[$field], 'to' => $data[$field] === '' ? null : $data[$field]];
            }
        }
        if ($changes) {
            $action = isset($changes['role_id']) ? 'user.role_changed' : 'user.updated';
            AuditLogger::log($action, 'user', $id, "Updated user {$data['email']}", ['changes' => $changes]);
        }

        Session::flash('success', 'Changes saved.');
        Response::redirect('/admin/users');
    }

    public function resetPassword(int $id): void
    {
        $user = $this->find($id);
        $temporary = $this->temporaryPassword();
        DB::run('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?',
            [password_hash($temporary, PASSWORD_DEFAULT, ['cost' => 12]), $id]);
        AuditLogger::log('user.password_reset', 'user', $id, "Reset password for {$user['email']}");

        Session::set('_temp_password', ['user' => $user['full_name'], 'password' => $temporary]);
        Session::flash('success', "Password reset for {$user['full_name']}. They must choose a new one when they sign in.");
        Response::redirect('/admin/users');
    }

    public function unlock(int $id): void
    {
        $user = $this->find($id);
        DB::transaction(static function () use ($id, $user): void {
            DB::run('UPDATE users SET locked_until = NULL WHERE id = ?', [$id]);
            DB::run('DELETE FROM login_attempts WHERE email = ? AND succeeded = 0', [$user['email']]);
        });
        AuditLogger::log('user.unlocked', 'user', $id, "Unlocked account {$user['email']}");
        Session::flash('success', "{$user['full_name']} can sign in again.");
        Response::redirect('/admin/users');
    }

    // -----------------------------------------------------------------------

    private function validated(string $formPath, ?int $ignoreId = null): array
    {
        $data = Request::only(self::FIELDS);
        $data['email'] = mb_strtolower((string) $data['email']);
        $data['is_active'] = isset($_POST['is_active']) ? '1' : '0';

        $v = Validator::make($data, [
            'full_name'     => 'required|max:120',
            'email'         => 'required|email|max:190',
            'phone'         => 'max:30',
            'job_title'     => 'max:100',
            'role_id'       => 'required|int|exists:roles,id',
            'department_id' => 'int|exists:departments,id',
        ], [
            'full_name' => 'Full name', 'role_id' => 'role', 'department_id' => 'department', 'job_title' => 'Job title',
        ]);

        $clash = DB::value('SELECT id FROM users WHERE email = ? AND id <> ?', [$data['email'], $ignoreId ?? 0]);
        if ($clash !== null) {
            $v->addError('email', 'Another user already has this email address.');
        }

        if ($v->fails()) {
            back_with_errors($v->errors(), $formPath, $data);
        }
        return $data;
    }

    private function find(int $id): array
    {
        $user = DB::one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) {
            throw new HttpException(404);
        }
        return $user;
    }

    private function roles(): array
    {
        return DB::all('SELECT id, slug, name, description FROM roles ORDER BY id');
    }

    private function departments(): array
    {
        return DB::all('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name');
    }

    /** Readable but strong: e.g. "Kite-Harbor-4729!" (passes PasswordPolicy). */
    private function temporaryPassword(): string
    {
        $words = ['Amber', 'Brook', 'Cedar', 'Delta', 'Ember', 'Falcon', 'Grove', 'Harbor', 'Island', 'Juniper',
                  'Kite', 'Lumen', 'Maple', 'Nova', 'Orbit', 'Pine', 'Quartz', 'River', 'Summit', 'Tide'];
        $symbols = ['!', '#', '$', '%', '*', '?'];
        $password = $words[random_int(0, 19)] . '-' . $words[random_int(0, 19)] . '-'
            . random_int(1000, 9999) . $symbols[random_int(0, 5)];
        if (PasswordPolicy::check($password) !== []) { // cannot normally happen; never hand out a weak one
            throw new \LogicException('Generated temporary password failed policy');
        }
        return $password;
    }
}
