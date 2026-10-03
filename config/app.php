<?php
declare(strict_types=1);

return [
    'name'     => env('APP_NAME', 'RUMA IT Support'),
    'env'      => env('APP_ENV', 'production'),
    'debug'    => env_bool('APP_DEBUG', false),
    'url'      => rtrim((string) env('APP_URL', ''), '/'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    'db' => [
        'host'     => env('DB_HOST', 'localhost'),
        'port'     => (int) env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'ruma_itsm'),
        'username' => env('DB_USERNAME', ''),
        'password' => env('DB_PASSWORD', ''),
    ],

    'session' => [
        'name'           => env('SESSION_NAME', 'ruma_sid'),
        'idle_minutes'   => (int) env('SESSION_IDLE_MINUTES', 30),
        'absolute_hours' => (int) env('SESSION_ABSOLUTE_HOURS', 12),
        'secure'         => env('SESSION_SECURE', 'auto'),
    ],

    'login' => [
        'max_attempts'    => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'lock_minutes'    => (int) env('LOGIN_LOCK_MINUTES', 15),
        'ip_max_attempts' => (int) env('LOGIN_IP_MAX_ATTEMPTS', 30),
    ],

    'trusted_proxies' => array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))),

    'uploads' => [
        'max_mb' => (int) env('UPLOAD_MAX_MB', 5),
        'path'   => BASE_PATH . '/storage/uploads',
    ],

    'mail' => [
        'enabled'    => env_bool('MAIL_ENABLED', false),
        'host'       => env('MAIL_HOST', ''),
        'port'       => (int) env('MAIL_PORT', 587),
        'username'   => env('MAIL_USERNAME', ''),
        'password'   => env('MAIL_PASSWORD', ''),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'from'       => env('MAIL_FROM_ADDRESS', 'it-support@ruma.hospital'),
        'from_name'  => env('MAIL_FROM_NAME', 'RUMA IT Support'),
    ],
];
