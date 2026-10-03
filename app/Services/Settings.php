<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class Settings
{
    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (DB::all('SELECT setting_key, setting_value FROM settings') as $row) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
        }
        return self::$cache[$key] ?? $default;
    }
}
