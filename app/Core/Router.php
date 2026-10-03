<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\AuditLogger;

/**
 * Route options:
 *   auth  (bool, default true)  — must be signed in
 *   guest (bool)                — signed-in users are redirected to the dashboard
 *   can   (string|string[])     — permission(s); any one is enough
 *   allow_password_change (bool)— reachable while a forced password change is pending
 */
final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $options = []): void
    {
        $this->add('GET', $path, $handler, $options);
    }

    public function post(string $path, array $handler, array $options = []): void
    {
        $this->add('POST', $path, $handler, $options);
    }

    private function add(string $method, string $path, array $handler, array $options): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[0-9]+)', rtrim($path, '/') ?: '/') . '$#';
        $this->routes[] = compact('method', 'path', 'regex', 'handler', 'options');
    }

    public function dispatch(): void
    {
        $method = Request::method();
        $path = Request::path();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $method) {
                continue;
            }
            $params = array_map('intval', array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));
            $this->runMiddleware($route['options']);

            [$class, $action] = $route['handler'];
            (new $class())->$action(...$params);
            return;
        }

        throw new HttpException($pathMatched ? 405 : 404);
    }

    private function runMiddleware(array $opt): void
    {
        $method = Request::method();

        // CSRF applies to every state-changing request, signed in or not.
        if ($method !== 'GET' && $method !== 'HEAD' && !Csrf::verify()) {
            AuditLogger::log('security.csrf_failed', null, null, 'Rejected request with missing or invalid CSRF token', [
                'path' => $method . ' ' . Request::path(),
            ]);
            throw new HttpException(419);
        }

        if (!empty($opt['guest'])) {
            if (Auth::check()) {
                Response::redirect('/');
            }
            return;
        }

        if (($opt['auth'] ?? true) === false) {
            return;
        }

        if (!Auth::check()) {
            if (Request::wantsJson()) {
                Response::json(['error' => 'unauthenticated'], 401);
            }
            if ($method === 'GET') {
                Session::set('intended', Request::path() . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']));
            }
            Response::redirect('/login');
        }

        // Authenticated pages may contain sensitive data: never cache them.
        header('Cache-Control: no-store, max-age=0');
        header('Pragma: no-cache');

        if ((int) Auth::user()['must_change_password'] === 1 && empty($opt['allow_password_change'])) {
            if (Request::wantsJson()) {
                Response::json(['error' => 'password_change_required'], 403);
            }
            Response::redirect('/account/password');
        }

        if (!empty($opt['can'])) {
            Gate::authorize(...(array) $opt['can']);
        }
    }
}
