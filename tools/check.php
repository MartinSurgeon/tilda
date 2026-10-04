<?php
declare(strict_types=1);

// Deployment self-check. Run after installing or upgrading:
//   php tools/check.php
// Exit code 0 = no failures (warnings allowed), 1 = something must be fixed.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Services\HashChain;

$failures = 0;
$warnings = 0;
$report = static function (string $status, string $message) use (&$failures, &$warnings): void {
    $failures += $status === 'FAIL' ? 1 : 0;
    $warnings += $status === 'WARN' ? 1 : 0;
    echo str_pad("[{$status}]", 7) . $message . PHP_EOL;
};
$check = static fn (bool $ok, string $pass, string $fail, string $level = 'FAIL') => $report($ok ? 'PASS' : $level, $ok ? $pass : $fail);
$bytes = static function (string $v): int {
    $n = (int) $v;
    return match (strtolower(substr(trim($v), -1))) { 'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n };
};
$prod = config('env') === 'production';

echo "RUMA IT Support: deployment check (" . ($prod ? 'production' : config('env')) . ")\n\n";

// PHP -------------------------------------------------------------------------
$check(PHP_VERSION_ID >= 80100, 'PHP ' . PHP_VERSION, 'PHP 8.1 or newer is required (found ' . PHP_VERSION . ')');
foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'gd', 'openssl', 'dom'] as $ext) {
    $check(extension_loaded($ext), "Extension {$ext}", "Missing PHP extension {$ext}");
}
$check(function_exists('imagewebp'), 'GD WebP support', 'GD has no WebP support: WebP uploads will fail', 'WARN');
$check(extension_loaded('exif'), 'Extension exif (photo rotation)', 'exif missing: phone photos may appear rotated', 'WARN');
$maxMb = (int) config('uploads.max_mb');
$check($bytes((string) ini_get('upload_max_filesize')) >= $maxMb * 1024 ** 2,
    'upload_max_filesize ' . ini_get('upload_max_filesize'), "upload_max_filesize is below UPLOAD_MAX_MB ({$maxMb} MB)");
$check($bytes((string) ini_get('post_max_size')) >= 3 * $maxMb * 1024 ** 2,
    'post_max_size ' . ini_get('post_max_size'), 'post_max_size should be at least 3 × UPLOAD_MAX_MB (3 files per message)', 'WARN');

// Configuration ---------------------------------------------------------------
$check(is_file(BASE_PATH . '/.env'), '.env present', '.env is missing: copy .env.example and fill it in');
if ($prod) {
    $check(!config('debug'), 'APP_DEBUG is off', 'APP_DEBUG must be false in production (it shows error details)');
    $check(str_starts_with((string) config('url'), 'https://'), 'APP_URL uses https', 'APP_URL should start with https://');
    $check(in_array(strtolower((string) config('session.secure')), ['true', '1', 'yes'], true), 'SESSION_SECURE=true',
        'Set SESSION_SECURE=true in production so the session cookie is never sent over plain HTTP', 'WARN');
    $check((bool) config('mail.enabled'), 'Email sending enabled', 'MAIL_ENABLED=false: notifications are only written to storage/logs/mail.log', 'WARN');
}
$check((string) config('timezone') !== 'UTC' || !$prod, 'Timezone ' . config('timezone'), 'APP_TIMEZONE is UTC: set the hospital\'s local timezone so reports use local days', 'WARN');

// Storage ---------------------------------------------------------------------
foreach (['storage/uploads', 'storage/logs', 'storage/cache', 'storage/sessions'] as $dir) {
    $check(is_dir(BASE_PATH . "/{$dir}") && is_writable(BASE_PATH . "/{$dir}"), "{$dir} writable", "{$dir} is missing or not writable by PHP");
}
$check(is_file(BASE_PATH . '/vendor/autoload.php'), 'Composer dependencies installed', 'vendor/ missing: run php tools/composer.phar install --no-dev');
$check(is_file(BASE_PATH . '/public/assets/css/app.css'), 'Built CSS present', 'public/assets/css/app.css missing: run npm run build');

// Database --------------------------------------------------------------------
try {
    DB::pdo();
    $report('PASS', 'Database connection (' . DB::value('SELECT VERSION()') . ')');
} catch (Throwable $e) {
    $report('FAIL', 'Cannot connect to the database: ' . $e->getMessage());
    echo "\n{$failures} failure(s), {$warnings} warning(s).\n";
    exit(1);
}
$tables = DB::run('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$missing = array_diff(['users', 'tickets', 'audit_logs', 'db_change_logs', 'audit_chain_head', 'sla_policies'], $tables);
$check(!$missing, 'Schema installed', 'Missing tables: ' . implode(', ', $missing) . ' (import database/schema.sql)');

$triggers = DB::run('SHOW TRIGGERS')->fetchAll(PDO::FETCH_COLUMN);
$auditTriggers = array_intersect(['trg_audit_logs_bi', 'trg_audit_logs_bu', 'trg_audit_logs_bd', 'trg_db_change_logs_bi', 'trg_db_change_logs_bu', 'trg_db_change_logs_bd', 'trg_audit_chain_head_bd'], $triggers);
$check(count($auditTriggers) === 7, 'Audit protection triggers (7)', 'Audit protection triggers missing: import database/triggers.sql');
$changeTriggers = count(array_filter($triggers, static fn ($t) => preg_match('/^trg_(?!audit_|db_change_logs_)\w+_a[iud]$/', $t)));
$check($changeTriggers >= 42, "Data change triggers ({$changeTriggers})", "Only {$changeTriggers} data change triggers: import database/change_triggers.sql");

foreach (['audit_logs', 'db_change_logs'] as $chain) {
    $r = HashChain::verify($chain);
    $check($r['ok'], "Hash chain {$chain} intact ({$r['checked']} entries)", "Hash chain {$chain} FAILED at entry #{$r['first_bad_id']}: {$r['problem']}");
}

// Accounts --------------------------------------------------------------------
$demoPassword = 0;
foreach (DB::all('SELECT email, password_hash FROM users WHERE is_active = 1') as $u) {
    if (password_verify('Ruma@2026!', $u['password_hash'])) {
        $demoPassword++;
    }
}
$check($demoPassword === 0, 'No active account uses the demo password',
    "{$demoPassword} active account(s) still use the demo password Ruma@2026!", $prod ? 'FAIL' : 'WARN');
$demoUsers = (int) DB::value("SELECT COUNT(*) FROM users WHERE is_active = 1 AND (email LIKE 'demo.%' OR email IN ('admin@ruma.hospital','employee@ruma.hospital','tech@ruma.hospital','tech2@ruma.hospital','management@ruma.hospital','auditor@ruma.hospital'))");
$check($demoUsers === 0, 'No demo accounts active', "{$demoUsers} demo account(s) are active: deactivate them before go-live", $prod ? 'FAIL' : 'WARN');
$admins = (int) DB::value("SELECT COUNT(*) FROM users u JOIN role_permissions rp ON rp.role_id = u.role_id JOIN permissions p ON p.id = rp.permission_id WHERE p.slug = 'admin.users' AND u.is_active = 1");
$check($admins >= 1, "{$admins} active administrator(s)", 'No active administrator: nobody can manage users');

// Background jobs ---------------------------------------------------------------
$lastAnchor = DB::value("SELECT MAX(created_at) FROM audit_logs WHERE action = 'audit.anchor'");
$check($lastAnchor !== null && strtotime((string) $lastAnchor) > time() - 2 * 86400,
    'Cron is running (last daily fingerprint ' . ($lastAnchor ?? 'never') . ')',
    'No daily audit fingerprint in 2 days: is the cron job for tools/cron.php set up?', 'WARN');
$failedMail = (int) DB::value("SELECT COUNT(*) FROM email_queue WHERE status = 'failed' AND created_at > NOW() - INTERVAL 7 DAY");
$check($failedMail === 0, 'No failed emails in the last 7 days', "{$failedMail} email(s) failed in the last 7 days: check MAIL_* settings", 'WARN');

echo "\n{$failures} failure(s), {$warnings} warning(s).\n";
exit($failures > 0 ? 1 : 0);
