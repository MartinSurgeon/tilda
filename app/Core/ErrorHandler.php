<?php
declare(strict_types=1);

namespace App\Core;

final class ErrorHandler
{
    private const TITLES = [
        403 => 'You do not have access to this page',
        404 => 'We could not find that page',
        405 => 'That action is not allowed here',
        419 => 'Your form expired',
        500 => 'Something went wrong on our side',
    ];

    public static function register(bool $debug): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');

        set_error_handler(static function (int $no, string $msg, string $file, int $line): bool {
            if (!(error_reporting() & $no)) {
                return false;
            }
            throw new \ErrorException($msg, 0, $no, $file, $line);
        });

        set_exception_handler(static function (\Throwable $e) use ($debug): void {
            $status = $e instanceof HttpException ? $e->status : 500;
            if ($status === 500) {
                self::logException($e);
            }
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            self::render($status, $debug && $status === 500 ? $e : null);
        });
    }

    public static function render(int $status, ?\Throwable $debugError = null): void
    {
        if (!headers_sent()) {
            http_response_code($status);
        }
        if (Request::wantsJson()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['error' => self::TITLES[$status] ?? 'Error', 'status' => $status]);
            return;
        }
        try {
            $layout = (session_status() === PHP_SESSION_ACTIVE && Auth::check()) ? 'app' : 'auth';
            echo View::render('errors/error', [
                'title'  => self::TITLES[$status] ?? 'Error',
                'status' => $status,
                'debug'  => $debugError,
            ], $layout);
        } catch (\Throwable $inner) {
            self::logException($inner);
            echo '<!doctype html><title>Error</title><p>Something went wrong. Please try again or contact IT support.</p>';
        }
    }

    private static function logException(\Throwable $e): void
    {
        $line = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n",
            date('c'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        @file_put_contents(BASE_PATH . '/storage/logs/app.log', $line, FILE_APPEND | LOCK_EX);
    }
}
