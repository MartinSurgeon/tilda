<?php
declare(strict_types=1);

// Background jobs. Schedule every 5 minutes, e.g. cPanel → Cron Jobs:
//   */5 * * * * /usr/local/bin/php /home/CPANELUSER/ruma/tools/cron.php >> /home/CPANELUSER/ruma/storage/logs/cron.log 2>&1
//
// Run by hand: php tools/cron.php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Services\Scheduler;

// One run at a time, even if a previous run is slow.
$lock = fopen(BASE_PATH . '/storage/cache/cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo date('c') . " previous run still active, skipping\n";
    exit(0);
}

DB::setContext(null, 'cron', 'tools/cron.php');

$jobs = [
    'sla_warnings' => [Scheduler::class, 'slaWarnings'],
    'sla_breaches' => [Scheduler::class, 'slaBreaches'],
    'auto_close'   => [Scheduler::class, 'autoClose'],
    'audit_anchor' => [Scheduler::class, 'auditAnchor'],
    'send_mail'    => [Scheduler::class, 'sendMail'],
    'housekeeping' => [Scheduler::class, 'housekeeping'],
];

$exit = 0;
foreach ($jobs as $name => $job) {
    try {
        $result = $job();
        echo date('c') . " {$name}: " . (is_array($result) ? json_encode($result) : $result) . "\n";
    } catch (Throwable $e) {
        // One failing job must not stop the others.
        $exit = 1;
        echo date('c') . " {$name} FAILED: " . $e->getMessage() . "\n";
    }
}

flock($lock, LOCK_UN);
exit($exit);
