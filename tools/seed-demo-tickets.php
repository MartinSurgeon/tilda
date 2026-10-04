<?php
declare(strict_types=1);

// DEMO DATA ONLY. Fills the system with realistic ticket history (about 75
// days) so dashboards and reports can be reviewed. Never run in production:
// every row is recorded in the audit trail as created by "demo-seed".
//
//   php tools/seed-demo-tickets.php [--count=180] [--force]

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Services\Sla;

$args = getopt('', ['count::', 'force']);
if (config('env') === 'production' && !isset($args['force'])) {
    fwrite(STDERR, "Refusing to add demo tickets in production (APP_ENV=production).\n");
    exit(1);
}
$count = max(1, (int) ($args['count'] ?? 180));
mt_srand(20261004); // repeatable demo data

DB::setContext(null, 'demo-seed', 'tools/seed-demo-tickets.php');

$pick = static function (array $weighted) {
    $r = mt_rand(1, array_sum($weighted));
    foreach ($weighted as $key => $w) {
        if (($r -= $w) <= 0) {
            return $key;
        }
    }
    return array_key_first($weighted);
};
$at = static fn (int $ts) => date('Y-m-d H:i:s', $ts);

// Demo staff across departments (same demo password; must change on first sign-in).
$hash = (string) DB::value('SELECT password_hash FROM users WHERE id = 4');
$staff = [
    ['Grace Mensah', 'demo.grace', 2, 'Senior Nurse'], ['Peter Otieno', 'demo.peter', 4, 'Radiographer'],
    ['Amina Bello', 'demo.amina', 5, 'Lab Scientist'], ['Kwame Asare', 'demo.kwame', 6, 'Pharmacist'],
    ['Joyce Banda', 'demo.joyce', 7, 'Midwife'], ['Samuel Kariuki', 'demo.samuel', 3, 'Clinical Officer'],
    ['Fatima Diallo', 'demo.fatima', 10, 'Accountant'], ['Daniel Mutua', 'demo.daniel', 8, 'Theatre Nurse'],
];
$requesters = [4 => 1];
foreach ($staff as [$name, $local, $dept, $title]) {
    $email = $local . '@ruma.hospital';
    $id = DB::value('SELECT id FROM users WHERE email = ?', [$email]);
    if (!$id) {
        $id = DB::insert('INSERT INTO users (role_id, department_id, full_name, email, job_title, password_hash, must_change_password) VALUES (1, ?, ?, ?, ?, ?, 1)',
            [$dept, $name, $email, $title, $hash]);
    }
    $requesters[(int) $id] = $dept;
}
$requesters[4] = 1;

$categories = [];
foreach (DB::all('SELECT id, parent_id FROM categories WHERE is_active = 1') as $c) {
    if ($c['parent_id'] === null) {
        $categories[(int) $c['id']] ??= [];
    } else {
        $categories[(int) $c['parent_id']][] = (int) $c['id'];
    }
}
$catWeights = [1 => 38, 2 => 30, 3 => 18, 4 => 10, 5 => 4];        // hardware is the most common
$prioWeights = [1 => 5, 2 => 20, 3 => 50, 4 => 25];
$locations = [
    1 => ['Triage desk', 'Resus bay 2', 'Nurses station'], 2 => ['Bed 4 monitor', 'Nurses station', 'Bay B'],
    3 => ['Reception', 'Consulting room 3', 'Consulting room 7'], 4 => ['CT control room', 'X-ray room 1', 'Reporting room'],
    5 => ['Sample reception', 'Haematology bench', 'Lab office'], 6 => ['Dispensary counter', 'Store room'],
    7 => ['Labour ward desk', 'Postnatal ward'], 8 => ['Theatre 2', 'Recovery'], 10 => ['Cashier window 1', 'Finance office'],
];
$titles = [
    1 => ['Printer jams on every page', 'Computer will not start', 'Screen flickering', 'Label printer not printing', 'Keyboard not working', 'Barcode scanner not reading'],
    2 => ['HMS freezes when saving notes', 'Cannot open patient list in HMS', 'Email not sending', 'Lab results not showing in HMS', 'PACS images slow to load'],
    3 => ['No internet on ward PCs', 'Wi-Fi keeps dropping', 'Network very slow', 'Cannot connect to VPN'],
    4 => ['Locked out of HMS', 'Forgot password', 'New staff member needs an account', 'Need access to pharmacy module'],
    5 => ['Request for a second monitor', 'Question about printing'],
];
$techs = [2 => 5, 3 => 4, 1 => 1];
$now = time();
$made = 0;

for ($i = 0; $i < $count; $i++) {
    // Weekdays and working hours are busier.
    do {
        $created = $now - mt_rand(3600, 75 * 86400);
        $dow = (int) date('N', $created);
        $hour = (int) date('G', $created);
    } while (($dow >= 6 && mt_rand(1, 3) > 1) || (($hour < 7 || $hour > 19) && mt_rand(1, 4) > 1));

    $requester = (int) array_rand($requesters);
    $dept = $requesters[$requester];
    $cat = (int) $pick($catWeights);
    $sub = $categories[$cat] ? $categories[$cat][array_rand($categories[$cat])] : null;
    $prio = (int) $pick($prioWeights);
    $title = $titles[$cat][array_rand($titles[$cat])];
    $loc = $locations[$dept][array_rand($locations[$dept])] ?? '';
    $policy = Sla::policy($prio);
    $due = Sla::dueDates($prio, $at($created));
    $age = $now - $created;

    // Lifecycle depends on age; most tickets older than a couple of days are done.
    $tech = (int) $pick($techs);
    $respond = $created + (int) ($policy['response_minutes'] * 60 * (mt_rand(10, 140) / 100));
    $fixTarget = $policy['resolution_minutes'] * 60;
    $fixed = $created + (int) ($fixTarget * (mt_rand(15, 135) / 100)); // ~80% within target
    $hold = mt_rand(1, 10) === 1 ? mt_rand(60, 600) : 0;
    $fixed = max($fixed, $respond + 600) + $hold * 60; // never fixed before IT first responded

    $status = 'open';
    $assignee = null;
    $firstResponse = null;
    $resolvedAt = null;
    $closedAt = null;
    if ($respond < $now) {
        $assignee = $tech;
        $firstResponse = $respond;
        $status = 'in_progress';
        if ($fixed < $now && ($age > 2 * 86400 ? mt_rand(1, 100) <= 93 : mt_rand(1, 2) === 1)) {
            $resolvedAt = $fixed;
            $status = 'resolved';
            if ($fixed + 2 * 86400 < $now) {
                $closedAt = $fixed + mt_rand(3600, 2 * 86400);
                $status = 'closed';
            }
        } elseif (mt_rand(1, 6) === 1) {
            $status = 'on_hold';
        }
    }

    DB::transaction(static function () use (&$made, $created, $requester, $dept, $cat, $sub, $prio, $title, $loc, $due, $status, $assignee,
        $firstResponse, $resolvedAt, $closedAt, $hold, $at, $respond, $tech): void {
        $year = (int) date('Y', $created);
        DB::run('INSERT INTO ticket_sequences (seq_year, last_value) VALUES (?, LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(last_value + 1)', [$year]);
        $ref = sprintf('%s-%d-%06d', setting('tickets.ref_prefix', 'RUMA'), $year, (int) DB::value('SELECT LAST_INSERT_ID()'));
        $resolveDue = date('Y-m-d H:i:s', strtotime($due['resolve_due_at']) + $hold * 60);

        $id = DB::insert(
            'INSERT INTO tickets (ref, title, description, category_id, subcategory_id, department_id, location, priority_id, status,
                                  requester_id, assignee_id, response_due_at, resolve_due_at, first_response_at, resolved_at, closed_at,
                                  hold_minutes, on_hold_since, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$ref, $title, 'Demo ticket generated for review. ' . $title . '.', $cat, $sub, $dept, $loc, $prio, $status,
             $requester, $assignee, $due['response_due_at'], $resolveDue,
             $firstResponse ? $at($firstResponse) : null, $resolvedAt ? $at($resolvedAt) : null, $closedAt ? $at($closedAt) : null,
             $hold, $status === 'on_hold' ? $at($respond + 3600) : null, $at($created), $at($closedAt ?? $resolvedAt ?? $firstResponse ?? $created)]
        );
        $h = static fn (?string $from, string $to, ?int $by, int $ts, string $note = '') =>
            DB::run('INSERT INTO ticket_status_history (ticket_id, from_status, to_status, changed_by, note, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$id, $from, $to, $by, $note, $at($ts)]);
        $h(null, 'open', $requester, $created);
        if ($assignee) {
            $h('open', 'assigned', $tech, $respond, 'Assigned');
            $h('assigned', 'in_progress', $tech, $respond + 300);
            DB::run('INSERT INTO ticket_comments (ticket_id, user_id, body, created_at) VALUES (?, ?, ?, ?)',
                [$id, $tech, 'Looking into this now.', $at($respond + 360)]);
        }
        if ($status === 'on_hold') {
            $h('in_progress', 'on_hold', $tech, $respond + 3600, 'Waiting for a replacement part.');
        }
        if ($resolvedAt) {
            $h('in_progress', 'resolved', $tech, $resolvedAt, 'Fixed and tested with the user.');
        }
        if ($closedAt) {
            $h('resolved', 'closed', $requester, $closedAt);
        }
        $made++;
    });
}

echo "Created {$made} demo tickets and " . count($staff) . " demo staff accounts.\n";
