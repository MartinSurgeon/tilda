<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env loader. Values are kept in a private array (not putenv) so they
 * never leak into child processes or phpinfo().
 */
final class Env
{
    private static array $vars = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $len = strlen($value);
            if ($len >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$len - 1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            self::$vars[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$vars[$key] ?? $default;
    }
}
