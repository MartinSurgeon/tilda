<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

if (!is_file(BASE_PATH . '/vendor/autoload.php')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Setup Required · RUMA IT Support</title>'
        . '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#f5f5f5;color:#2b2b2b;margin:0;padding:40px}'
        . '.box{max-width:640px;margin:40px auto;background:#fff;padding:32px;border-radius:12px;border:1px solid #e2e2e2;box-shadow:0 4px 12px rgba(0,0,0,0.06)}'
        . 'h1{font-size:22px;color:#b42318;margin:0 0 16px}p{line-height:1.6;font-size:15px;color:#545454}code{background:#f7f7f7;padding:3px 6px;border-radius:4px;border:1px solid #e2e2e2;font-size:13px;color:#0d8257}'
        . 'ol{padding-left:20px;line-height:1.8;color:#545454;font-size:14px}li{margin-bottom:8px}</style></head><body>'
        . '<div class="box"><h1>Missing Dependencies (vendor/autoload.php)</h1>'
        . '<p>The PHP Composer <code>vendor/</code> directory was not found on your hosting server.</p>'
        . '<ol><li><strong>Option A (Recommended):</strong> Pull or upload the <code>vendor/</code> directory into your project root.</li>'
        . '<li><strong>Option B:</strong> Run <code>composer install --no-dev --optimize-autoloader</code> in cPanel Terminal or SSH.</li></ol>'
        . '</div></body></html>';
    exit;
}

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/navigation.php';

if (!is_file(BASE_PATH . '/.env')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Configuration Required · RUMA IT Support</title>'
        . '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#f5f5f5;color:#2b2b2b;margin:0;padding:40px}'
        . '.box{max-width:640px;margin:40px auto;background:#fff;padding:32px;border-radius:12px;border:1px solid #e2e2e2;box-shadow:0 4px 12px rgba(0,0,0,0.06)}'
        . 'h1{font-size:22px;color:#b42318;margin:0 0 16px}p{line-height:1.6;font-size:15px;color:#545454}code{background:#f7f7f7;padding:3px 6px;border-radius:4px;border:1px solid #e2e2e2;font-size:13px;color:#0d8257}'
        . 'ol{padding-left:20px;line-height:1.8;color:#545454;font-size:14px}li{margin-bottom:8px}</style></head><body>'
        . '<div class="box"><h1>Missing Configuration (.env)</h1>'
        . '<p>The environment file <code>.env</code> was not found in your project root on cPanel.</p>'
        . '<ol><li>In cPanel File Manager, create or copy <code>.env.example</code> to <code>.env</code> in the project root.</li>'
        . '<li>Set your cPanel database name, database user, and database password in <code>.env</code>.</li>'
        . '<li>Set <code>APP_URL=http://ruma.rmgroupstrategies.com</code>.</li></ol>'
        . '</div></body></html>';
    exit;
}

App\Core\Env::load(BASE_PATH . '/.env');
date_default_timezone_set((string) config('timezone'));
mb_internal_encoding('UTF-8');

App\Core\ErrorHandler::register((bool) config('debug'));
