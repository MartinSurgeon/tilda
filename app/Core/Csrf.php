<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }
        return $token;
    }

    /** Accepts the token from a form field (_csrf) or the X-CSRF-Token header (fetch). */
    public static function verify(): bool
    {
        $sent = $_POST[self::KEY] ?? Request::header('X-CSRF-Token') ?? '';
        $expected = Session::get(self::KEY);
        return is_string($sent) && is_string($expected) && $expected !== '' && hash_equals($expected, $sent);
    }

    public static function rotate(): void
    {
        Session::set(self::KEY, bin2hex(random_bytes(32)));
    }
}
