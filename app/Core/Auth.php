<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\AuditLogger;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    // Verified against when the email is unknown, so response time does not
    // reveal whether an account exists.
    private const DUMMY_HASH = '$2y$12$GYT60yzLfj8300nPvsSh.OdbnnV804jYO1ZNdXHZH767kZ35kESTW';

    /**
     * @return array{ok: bool, reason?: string, user?: array}
     *   reason: invalid | locked | throttled
     */
    public static function attempt(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        $ip = Request::ip();
        $cfg = config('login');

        $ipFailures = (int) DB::value(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND succeeded = 0
             AND attempted_at > NOW() - INTERVAL ? MINUTE',
            [$ip, $cfg['lock_minutes']]
        );
        if ($ipFailures >= $cfg['ip_max_attempts']) {
            AuditLogger::log('auth.login_throttled', 'user', null, 'Login blocked: too many attempts from this IP address', ['email' => $email]);
            return ['ok' => false, 'reason' => 'throttled'];
        }

        // Counting by email (not user id) means unknown emails lock out exactly
        // like real ones, so lockout behaviour leaks nothing.
        $emailFailures = (int) DB::value(
            'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND succeeded = 0
             AND attempted_at > NOW() - INTERVAL ? MINUTE',
            [$email, $cfg['lock_minutes']]
        );
        $user = DB::one('SELECT * FROM users WHERE email = ?', [$email]);

        $lockedByAdmin = $user && $user['locked_until'] !== null && strtotime($user['locked_until']) > time();
        if ($emailFailures >= $cfg['max_attempts'] || $lockedByAdmin) {
            AuditLogger::log('auth.login_locked', 'user', $user['id'] ?? null, 'Login refused: account temporarily locked', ['email' => $email]);
            return ['ok' => false, 'reason' => 'locked'];
        }

        $valid = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);
        $ok = $valid && $user && (int) $user['is_active'] === 1;

        DB::run('INSERT INTO login_attempts (email, ip_address, succeeded) VALUES (?, ?, ?)', [$email, $ip, $ok ? 1 : 0]);

        if (!$ok) {
            AuditLogger::log('auth.login_failed', 'user', $user['id'] ?? null, 'Failed sign-in', [
                'email'    => $email,
                'inactive' => $valid && $user && (int) $user['is_active'] === 0,
            ]);
            if ($user && $emailFailures + 1 >= $cfg['max_attempts']) {
                DB::run('UPDATE users SET locked_until = NOW() + INTERVAL ? MINUTE WHERE id = ?', [$cfg['lock_minutes'], $user['id']]);
                AuditLogger::log('auth.account_locked', 'user', $user['id'], 'Account locked after repeated failed sign-ins', ['email' => $email]);
            }
            return ['ok' => false, 'reason' => 'invalid'];
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT, ['cost' => 12])) {
            DB::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]), $user['id']]);
        }

        return ['ok' => true, 'user' => $user];
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Csrf::rotate();
        Session::set('user_id', (int) $user['id']);
        Session::set('auth_at', time());
        DB::run('UPDATE users SET last_login_at = NOW(), locked_until = NULL WHERE id = ?', [$user['id']]);
        self::$loaded = false;
        DB::setContext((int) $user['id'], Request::ip(), Request::userAgent());
        AuditLogger::log('auth.login', 'user', $user['id'], 'Signed in');
    }

    public static function logout(): void
    {
        if (self::check()) {
            AuditLogger::log('auth.logout', 'user', self::id(), 'Signed out');
        }
        Session::destroy();
        self::$user = null;
        self::$loaded = true;
    }

    /** Current user with role slug and department name, or null. Inactive users are signed out. */
    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('user_id');
            self::$user = $id ? DB::one(
                'SELECT u.id, u.full_name, u.email, u.role_id, u.department_id, u.job_title, u.phone,
                        u.must_change_password, u.is_active, u.password_changed_at, r.slug AS role, r.name AS role_name,
                        d.name AS department_name
                 FROM users u
                 JOIN roles r ON r.id = u.role_id
                 LEFT JOIN departments d ON d.id = u.department_id
                 WHERE u.id = ?',
                [$id]
            ) : null;
            // A password change or admin reset ends every session that signed in before it.
            $staleSession = self::$user && self::$user['password_changed_at'] !== null
                && strtotime(self::$user['password_changed_at']) >= (int) Session::get('auth_at', 0);
            if ($staleSession) {
                Session::destroy();
                session_id(session_create_id());
                session_start();
                Session::flash('warning', 'Your password was changed, so you have been signed out. Please sign in again.');
                self::$user = null;
            }
            if (self::$user && (int) self::$user['is_active'] !== 1) {
                Session::destroy();
                self::$user = null;
            }
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    /** Drop the cached user so the next call re-reads from the database. */
    public static function refresh(): void
    {
        self::$loaded = false;
    }
}
