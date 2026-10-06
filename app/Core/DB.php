<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper. Every query goes through prepared statements with native
 * (non-emulated) prepares, so user input never becomes part of SQL text.
 */
final class DB
{
    private static ?PDO $pdo = null;
    private static int $queries = 0;

    /** Number of queries this request (sent as X-Debug-Queries when APP_DEBUG=true). */
    public static function queryCount(): int
    {
        return self::$queries;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = config('db');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['database']);
            try {
                self::$pdo = new PDO($dsn, $c['username'], $c['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]);
                // Keep MySQL's clock in step with PHP's so NOW() and date() agree.
                self::$pdo->exec("SET time_zone = '" . (new \DateTime())->format('P') . "'");
                self::$pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            } catch (\PDOException $e) {
                if (config('debug')) {
                    throw $e;
                }
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
                echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Database Connection Error · RUMA IT Support</title>'
                    . '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#f5f5f5;color:#2b2b2b;margin:0;padding:40px}'
                    . '.box{max-width:640px;margin:40px auto;background:#fff;padding:32px;border-radius:12px;border:1px solid #e2e2e2;box-shadow:0 4px 12px rgba(0,0,0,0.06)}'
                    . 'h1{font-size:22px;color:#b42318;margin:0 0 16px}p{line-height:1.6;font-size:15px;color:#545454}code{background:#f7f7f7;padding:3px 6px;border-radius:4px;border:1px solid #e2e2e2;font-size:13px;color:#0d8257}'
                    . 'ol{padding-left:20px;line-height:1.8;color:#545454;font-size:14px}li{margin-bottom:8px}'
                    . '.err{background:#fdecea;border:1px solid #fecdca;color:#b42318;padding:12px;border-radius:6px;font-size:13px;margin:16px 0}'
                    . '</style></head><body>'
                    . '<div class="box"><h1>Database Connection Failed</h1>'
                    . '<p>Could not connect to the MySQL database on <code>' . htmlspecialchars($c['host'], ENT_QUOTES, 'UTF-8') . '</code>.</p>'
                    . '<div class="err"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>'
                    . '<ol><li>Check your <code>.env</code> file credentials (<code>DB_DATABASE</code>, <code>DB_USERNAME</code>, <code>DB_PASSWORD</code>).</li>'
                    . '<li>In cPanel, verify that the database user has been granted <strong>ALL PRIVILEGES</strong> on the database.</li>'
                    . '<li>Verify that tables have been imported from <code>database/schema.sql</code> in phpMyAdmin.</li></ol>'
                    . '</div></body></html>';
                exit;
            }
        }
        return self::$pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        self::$queries++;
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : $key;
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    /** @template T @param callable():T $fn @return T */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Expose the acting user to database triggers, which copy these session
     * variables into db_change_logs (see database/triggers.sql).
     */
    public static function setContext(?int $userId, string $ip, string $userAgent): void
    {
        self::run('SET @app_user_id = ?, @app_ip = ?, @app_ua = ?', [$userId, $ip, mb_substr($userAgent, 0, 255)]);
    }
}
