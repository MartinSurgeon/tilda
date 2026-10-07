<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Router;
use App\Core\SecurityHeaders;
use App\Core\Session;

try {
    // PHP built-in dev server: let it serve real files (css, js, images) directly.
    if (PHP_SAPI === 'cli-server') {
        $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if ($file !== __FILE__ && is_file($file)) {
            return false;
        }
    }

    require dirname(__DIR__) . '/app/bootstrap.php';

    SecurityHeaders::send();
    Session::start();
    DB::setContext(Auth::id(), Request::ip(), Request::userAgent());

    $router = new Router();
    require BASE_PATH . '/config/routes.php';
    $router->dispatch();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Application Error · RUMA IT Support</title>'
        . '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#f5f5f5;color:#2b2b2b;margin:0;padding:40px}'
        . '.box{max-width:700px;margin:20px auto;background:#fff;padding:32px;border-radius:12px;border:1px solid #e2e2e2;box-shadow:0 4px 12px rgba(0,0,0,0.06)}'
        . 'h1{font-size:20px;color:#b42318;margin:0 0 16px}'
        . '.err{background:#fdecea;border:1px solid #fecdca;color:#b42318;padding:12px;border-radius:6px;font-size:13px;margin:16px 0;word-break:break-all}'
        . 'pre{background:#f7f7f7;padding:12px;border-radius:6px;border:1px solid #e2e2e2;font-size:12px;overflow-x:auto}'
        . '</style></head><body>'
        . '<div class="box"><h1>Application Error (500)</h1>'
        . '<div class="err"><strong>' . htmlspecialchars($e::class, ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>'
        . '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . ' (Line ' . $e->getLine() . ')</p>'
        . '<pre>' . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>'
        . '</div></body></html>';
    exit;
}
