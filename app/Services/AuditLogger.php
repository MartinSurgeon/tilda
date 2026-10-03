<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;

/**
 * Writes the activity trail (audit_logs). created_at, prev_hash and row_hash
 * are set by the BEFORE INSERT trigger, never by PHP.
 */
final class AuditLogger
{
    public static function log(
        string $action,
        ?string $entityType = null,
        int|string|null $entityId = null,
        string $description = '',
        array $metadata = [],
    ): void {
        $user = session_status() === PHP_SESSION_ACTIVE ? Auth::user() : null;
        $email = $user['email'] ?? ($metadata['email'] ?? null);

        try {
            DB::run(
                'INSERT INTO audit_logs
                    (user_id, user_email, action, entity_type, entity_id, description, metadata,
                     ip_address, user_agent, created_at, prev_hash, row_hash)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(6), ?, ?)',
                [
                    $user ? (int) $user['id'] : null,
                    $email,
                    $action,
                    $entityType,
                    $entityId === null ? null : (string) $entityId,
                    mb_substr($description, 0, 500),
                    $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    Request::ip(),
                    Request::userAgent(),
                    '', // prev_hash: set by trigger
                    '', // row_hash: set by trigger
                ]
            );
        } catch (\Throwable $e) {
            // An audit write must never be silently lost: fail the request.
            throw new \RuntimeException('Audit log write failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
