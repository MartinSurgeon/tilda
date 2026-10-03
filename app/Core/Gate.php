<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\AuditLogger;

/**
 * Central permission check. Permissions come from role_permissions, so an IT
 * Manager can change them without a code deploy. Every protected route and
 * every object-level check calls into here — UI hiding is cosmetic only.
 */
final class Gate
{
    private static ?array $permissions = null;

    public static function permissions(): array
    {
        if (self::$permissions === null) {
            $user = Auth::user();
            self::$permissions = $user ? array_flip(DB::run(
                'SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
                [(int) $user['role_id']]
            )->fetchAll(\PDO::FETCH_COLUMN)) : [];
        }
        return self::$permissions;
    }

    public static function allows(string $permission): bool
    {
        return isset(self::permissions()[$permission]);
    }

    public static function any(string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::allows($permission)) {
                return true;
            }
        }
        return false;
    }

    /** Throws 403 (and records the attempt) unless the user holds the permission. */
    public static function authorize(string ...$permissions): void
    {
        if (!self::any(...$permissions)) {
            self::deny(implode('|', $permissions));
        }
    }

    public static function deny(string $what): never
    {
        AuditLogger::log('access.denied', null, null, 'Access denied', [
            'required' => $what,
            'path'     => Request::method() . ' ' . Request::path(),
        ]);
        throw new HttpException(403);
    }

    public static function reset(): void
    {
        self::$permissions = null;
    }
}
