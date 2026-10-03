<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Recomputes the audit hash chains. The SQL expressions MUST match the ones in
 * database/triggers.sql byte-for-byte; the database computes both sides, so
 * there are no PHP/MySQL formatting differences to worry about.
 */
final class HashChain
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    private const EXPRESSIONS = [
        'audit_logs' => "SHA2(CONCAT_WS('|', prev_hash, IFNULL(user_id, ''), IFNULL(user_email, ''), action,
            IFNULL(entity_type, ''), IFNULL(entity_id, ''), description, IFNULL(metadata, ''),
            IFNULL(ip_address, ''), IFNULL(user_agent, ''), DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s.%f')), 256)",
        'db_change_logs' => "SHA2(CONCAT_WS('|', prev_hash, table_name, record_id, operation,
            IFNULL(old_values, ''), IFNULL(new_values, ''), IFNULL(user_id, ''), IFNULL(ip_address, ''),
            IFNULL(user_agent, ''), DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s.%f')), 256)",
    ];

    /**
     * @return array{chain: string, ok: bool, checked: int, head_matches: bool, first_bad_id: ?int, problem: ?string}
     */
    public static function verify(string $chain, int $batch = 5000): array
    {
        if (!isset(self::EXPRESSIONS[$chain])) {
            throw new \InvalidArgumentException("Unknown chain {$chain}");
        }
        $expr = self::EXPRESSIONS[$chain];
        $expectedPrev = self::GENESIS;
        $lastId = 0;
        $checked = 0;

        do {
            $rows = DB::all(
                "SELECT id, prev_hash, row_hash, {$expr} AS computed FROM `{$chain}` WHERE id > ? ORDER BY id LIMIT ?",
                [$lastId, $batch]
            );
            foreach ($rows as $row) {
                $checked++;
                if (!hash_equals($expectedPrev, $row['prev_hash'])) {
                    return self::result($chain, false, $checked, false, (int) $row['id'],
                        'Link broken: this entry does not follow the one before it (an entry was removed or inserted).');
                }
                if (!hash_equals($row['row_hash'], $row['computed'])) {
                    return self::result($chain, false, $checked, false, (int) $row['id'],
                        'Content changed: this entry no longer matches its recorded fingerprint.');
                }
                $expectedPrev = $row['row_hash'];
                $lastId = (int) $row['id'];
            }
        } while (count($rows) === $batch);

        $head = (string) DB::value('SELECT last_hash FROM audit_chain_head WHERE chain = ?', [$chain]);
        $headMatches = hash_equals($head, $expectedPrev);

        return self::result($chain, $headMatches, $checked, $headMatches, null,
            $headMatches ? null : 'The newest entries are missing: the chain ends earlier than recorded.');
    }

    private static function result(string $chain, bool $ok, int $checked, bool $headMatches, ?int $badId, ?string $problem): array
    {
        return [
            'chain'        => $chain,
            'ok'           => $ok,
            'checked'      => $checked,
            'head_matches' => $headMatches,
            'first_bad_id' => $badId,
            'problem'      => $problem,
        ];
    }
}
