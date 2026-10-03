<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private static ?string $basePath = null;

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** URL prefix when the app is served from a sub-folder ('' at domain root). */
    public static function basePath(): string
    {
        if (self::$basePath === null) {
            $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
            self::$basePath = rtrim($dir, '/');
        }
        return self::$basePath;
    }

    /** Request path relative to the app root, always starting with '/'. */
    public static function path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = self::basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim($path, '/');
        return $path;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    /** Only the listed POST fields, trimmed. Missing keys become ''. */
    public static function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $value = $_POST[$key] ?? '';
            $out[$key] = is_string($value) ? trim($value) : $value;
        }
        return $out;
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    public static function wantsJson(): bool
    {
        return str_contains((string) self::header('Accept'), 'application/json')
            || self::header('X-Requested-With') === 'fetch';
    }

    /** Background polling must not count as user activity for idle timeout. */
    public static function isBackground(): bool
    {
        return self::header('X-Background') === '1';
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        return self::fromTrustedProxy() && strtolower((string) self::header('X-Forwarded-Proto')) === 'https';
    }

    public static function ip(): string
    {
        if (PHP_SAPI === 'cli') {
            return 'cli';
        }
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (self::fromTrustedProxy()) {
            $forwarded = explode(',', (string) self::header('X-Forwarded-For'));
            $candidate = trim($forwarded[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
        return $remote;
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    private static function fromTrustedProxy(): bool
    {
        return in_array($_SERVER['REMOTE_ADDR'] ?? '', config('trusted_proxies'), true);
    }
}
