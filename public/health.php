<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

$basePath = dirname(__DIR__);
$checks = [];

// 1. PHP Version
$phpVersion = PHP_VERSION;
$phpOk = version_compare($phpVersion, '8.1.0', '>=');
$checks[] = [
    'name' => 'PHP Version (>= 8.1)',
    'ok' => $phpOk,
    'detail' => 'Current PHP: ' . $phpVersion,
    'help' => 'In cPanel, go to "Select PHP Version" or "MultiPHP Manager" and select PHP 8.1 or PHP 8.2.',
];

// 2. Required PHP Extensions
$reqExts = ['pdo', 'pdo_mysql', 'mbstring', 'fileinfo', 'session', 'json'];
$missingExts = [];
foreach ($reqExts as $ext) {
    if (!extension_loaded($ext)) {
        $missingExts[] = $ext;
    }
}
$checks[] = [
    'name' => 'PHP Extensions (' . implode(', ', $reqExts) . ')',
    'ok' => empty($missingExts),
    'detail' => empty($missingExts) ? 'All required extensions loaded' : 'Missing: ' . implode(', ', $missingExts),
    'help' => 'Enable missing extensions in cPanel "Select PHP Version" -> Extensions.',
];

// 3. Vendor Directory
$vendorAutoload = $basePath . '/vendor/autoload.php';
$vendorOk = is_file($vendorAutoload);
$checks[] = [
    'name' => 'Composer Vendor Directory',
    'ok' => $vendorOk,
    'detail' => $vendorOk ? 'vendor/autoload.php exists' : 'vendor/ directory is missing or incomplete',
    'help' => 'Pull the latest code from GitHub (which includes vendor/) or upload the vendor folder into your project root.',
];

// 4. .env File
$envFile = $basePath . '/.env';
$envOk = is_file($envFile);
$envVars = [];
if ($envOk) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $len = strlen($v);
        if ($len >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[$len - 1] === $v[0]) {
            $v = substr($v, 1, -1);
        }
        $envVars[$k] = $v;
    }
}
$checks[] = [
    'name' => 'Environment Configuration (.env)',
    'ok' => $envOk,
    'detail' => $envOk ? '.env file found' : '.env file missing in project root',
    'help' => 'Copy .env.example to .env in cPanel File Manager and enter your database details.',
];

// 5. Storage Directory Permissions
$storageDirs = [
    'storage/sessions' => $basePath . '/storage/sessions',
    'storage/logs'     => $basePath . '/storage/logs',
    'storage/cache'    => $basePath . '/storage/cache',
    'storage/uploads'  => $basePath . '/storage/uploads',
];
$unwritable = [];
foreach ($storageDirs as $name => $path) {
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    if (!is_writable($path)) {
        $unwritable[] = $name;
    }
}
$checks[] = [
    'name' => 'Storage Directory Writable',
    'ok' => empty($unwritable),
    'detail' => empty($unwritable) ? 'All storage directories are writable' : 'Not writable: ' . implode(', ', $unwritable),
    'help' => 'In cPanel File Manager, right-click the storage directory and set Permissions to 0755 or 0775.',
];

// 6. Database Connection
$dbOk = false;
$dbDetail = 'Waiting for .env';
$dbHelp = 'Set valid DB credentials in .env and create the database in cPanel MySQL Databases.';
if ($envOk) {
    $dbHost = $envVars['DB_HOST'] ?? 'localhost';
    $dbPort = (int) ($envVars['DB_PORT'] ?? 3306);
    $dbName = $envVars['DB_DATABASE'] ?? '';
    $dbUser = $envVars['DB_USERNAME'] ?? '';
    $dbPass = $envVars['DB_PASSWORD'] ?? '';

    if (empty($dbName) || empty($dbUser)) {
        $dbDetail = 'DB_DATABASE or DB_USERNAME is blank in .env';
    } else {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            $tableCount = count($tables);
            if ($tableCount === 0) {
                $dbOk = false;
                $dbDetail = 'Connected to database "' . htmlspecialchars($dbName) . '", but 0 tables found.';
                $dbHelp = 'Import database/schema.sql and database/seed.sql into this database in cPanel phpMyAdmin.';
            } else {
                $dbOk = true;
                $dbDetail = 'Connected successfully! Found ' . $tableCount . ' tables (users, tickets, etc.).';
                $dbHelp = '';
            }
        } catch (\PDOException $e) {
            $dbOk = false;
            $dbDetail = 'Connection failed: ' . $e->getMessage();
            $dbHelp = 'Verify DB_DATABASE, DB_USERNAME, and DB_PASSWORD in .env. In cPanel MySQL Databases, ensure the user has ALL PRIVILEGES on the database.';
        }
    }
}
$checks[] = [
    'name' => 'MySQL Database Connection',
    'ok' => $dbOk,
    'detail' => $dbDetail,
    'help' => $dbHelp,
];

$allPassed = true;
foreach ($checks as $c) {
    if (!$c['ok']) {
        $allPassed = false;
        break;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Server Diagnostics · RUMA IT Support</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f5; color: #2b2b2b; margin: 0; padding: 24px; }
        .wrap { max-width: 680px; margin: 20px auto; background: #fff; border-radius: 12px; border: 1px solid #e2e2e2; box-shadow: 0 4px 12px rgba(0,0,0,0.06); padding: 32px; }
        h1 { font-size: 22px; margin: 0 0 8px; color: #0d8257; }
        .summary { padding: 12px 16px; border-radius: 8px; margin: 16px 0 24px; font-weight: 600; font-size: 15px; }
        .summary-ok { background: #e8f5e8; color: #0b6b0d; border: 1px solid #b8e6b8; }
        .summary-err { background: #fdecea; color: #b42318; border: 1px solid #fecdca; }
        .check-item { border: 1px solid #e2e2e2; border-radius: 8px; padding: 14px 16px; margin-bottom: 12px; }
        .check-item.ok { border-left: 4px solid #0d840f; }
        .check-item.fail { border-left: 4px solid #b42318; background: #fffbfb; }
        .check-header { display: flex; justify-content: space-between; align-items: center; font-weight: 600; font-size: 14px; }
        .badge-ok { background: #e8f5e8; color: #0b6b0d; padding: 2px 8px; border-radius: 9999px; font-size: 12px; }
        .badge-fail { background: #fdecea; color: #b42318; padding: 2px 8px; border-radius: 9999px; font-size: 12px; }
        .check-detail { margin-top: 6px; font-size: 13px; color: #545454; }
        .check-help { margin-top: 6px; font-size: 12px; color: #b42318; background: #fef3f2; padding: 6px 10px; border-radius: 6px; }
        .next { margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e2e2; font-size: 13px; color: #545454; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>RUMA IT Support · Server Diagnostics</h1>
    <p style="color:#666;font-size:13px;margin:0">Health check for cPanel / LiteSpeed deployment</p>

    <div class="summary <?= $allPassed ? 'summary-ok' : 'summary-err' ?>">
        <?= $allPassed ? '✓ All systems operational! The application is ready.' : '⚠ Attention required: Some prerequisites are not met yet.' ?>
    </div>

    <?php foreach ($checks as $c): ?>
        <div class="check-item <?= $c['ok'] ? 'ok' : 'fail' ?>">
            <div class="check-header">
                <span><?= htmlspecialchars($c['name']) ?></span>
                <span class="<?= $c['ok'] ? 'badge-ok' : 'badge-fail' ?>"><?= $c['ok'] ? 'PASS' : 'FAIL' ?></span>
            </div>
            <div class="check-detail"><?= htmlspecialchars($c['detail']) ?></div>
            <?php if (!$c['ok'] && !empty($c['help'])): ?>
                <div class="check-help"><strong>Fix:</strong> <?= htmlspecialchars($c['help']) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="next">
        <?php if ($allPassed): ?>
            <p>You can now open <a href="./" style="color:#0d8257;font-weight:600">the application</a>. (Tip: delete or restrict <code>public/health.php</code> when finished.)</p>
        <?php else: ?>
            <p>Please resolve the failed checks above and refresh this page.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
