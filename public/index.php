<?php
declare(strict_types=1);

// PHP built-in dev server: let it serve real files (css, js, images) directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __FILE__ && is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Router;
use App\Core\SecurityHeaders;
use App\Core\Session;

SecurityHeaders::send();
Session::start();
DB::setContext(Auth::id(), Request::ip(), Request::userAgent());

$router = new Router();
require BASE_PATH . '/config/routes.php';
$router->dispatch();
