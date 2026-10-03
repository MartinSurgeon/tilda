<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    private const IDLE_KEY = '_last_activity';
    private const START_KEY = '_started_at';

    public static function start(): void
    {
        $cfg = config('session');
        $secure = $cfg['secure'] === 'auto' ? Request::isHttps() : filter_var($cfg['secure'], FILTER_VALIDATE_BOOL);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.gc_maxlifetime', (string) ($cfg['absolute_hours'] * 3600));
        // Own folder: on shared hosting the default /tmp may be shared with other accounts.
        session_save_path(BASE_PATH . '/storage/sessions');

        session_name($cfg['name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => Request::basePath() === '' ? '/' : Request::basePath() . '/',
            'secure'   => $secure,
            'httponly' => true,
            // Lax (not Strict) so links in notification emails keep the user signed in.
            // State-changing requests are POST-only and CSRF-protected regardless.
            'samesite' => 'Lax',
        ]);
        session_start();

        $now = time();
        $idle = $cfg['idle_minutes'] * 60;
        $absolute = $cfg['absolute_hours'] * 3600;
        $last = $_SESSION[self::IDLE_KEY] ?? $now;
        $started = $_SESSION[self::START_KEY] ?? $now;

        if (isset($_SESSION['user_id']) && ($now - $last > $idle || $now - $started > $absolute)) {
            self::destroy();
            session_id(session_create_id());
            session_start();
            self::flash('warning', 'You were signed out after a period of inactivity. Please sign in again.');
            $_SESSION['_expired'] = true;
        }

        $_SESSION[self::START_KEY] ??= $now;
        if (!Request::isBackground()) {
            $_SESSION[self::IDLE_KEY] = $now;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Pull a value and remove it (one-time read). */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $value;
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function takeFlashes(): array
    {
        return self::pull('_flash', []);
    }

    /** New session ID on privilege change (login, password change) to block fixation. */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION[self::START_KEY] = time();
        $_SESSION[self::IDLE_KEY] = time();
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'],
            ]);
        }
        session_destroy();
    }
}
