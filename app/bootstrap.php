<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/navigation.php';

App\Core\Env::load(BASE_PATH . '/.env');
date_default_timezone_set((string) config('timezone'));
mb_internal_encoding('UTF-8');

App\Core\ErrorHandler::register((bool) config('debug'));
