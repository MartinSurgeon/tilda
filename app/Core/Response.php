<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $path, int $status = 303): never
    {
        $target = preg_match('#^https?://#', $path) ? $path : url($path);
        header('Location: ' . $target, true, $status);
        exit;
    }

    /** Redirect back to the referring page when it is on this site, else to $fallback. */
    public static function back(string $fallback = '/'): never
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $refHost = parse_url($ref, PHP_URL_HOST);
        $ownHost = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        if ($refHost !== null && $refHost !== false && $refHost === $ownHost) {
            $path = (string) parse_url($ref, PHP_URL_PATH);
            $query = parse_url($ref, PHP_URL_QUERY);
            header('Location: ' . $path . ($query ? '?' . $query : ''), true, 303);
            exit;
        }
        self::redirect($fallback);
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function html(string $html, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        if (config('debug')) {
            header('X-Debug-Queries: ' . DB::queryCount());
        }
        echo $html;
        exit;
    }
}
