<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Audit;
use App\Services\AuditLogger;
use App\Services\Export;
use App\Services\HashChain;

/** Read-only audit viewer, exports and integrity check. There are no edit or delete routes. */
final class AuditController
{
    private const PER_PAGE = 50;
    private const PDF_LIMIT = 2000;

    public function activity(): void
    {
        $f = Audit::activityFilters();
        [$where, $p] = Audit::activityWhere($f);
        $page = max(1, (int) Request::query('page', 1));
        $total = Audit::count('audit_logs', 'a', $where, $p);
        $this->logView('activity', $f, $page);

        View::show('audit/activity', [
            'title'   => 'Activity log',
            'tab'     => 'activity',
            'filters' => $f,
            'rows'    => Audit::activity($f, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total'   => $total,
            'page'    => $page,
            'pages'   => max(1, (int) ceil($total / self::PER_PAGE)),
            'users'   => $this->users(),
            'actions' => Audit::actionGroups(),
            'entities'=> DB::run('SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type IS NOT NULL ORDER BY entity_type')->fetchAll(\PDO::FETCH_COLUMN),
        ]);
    }

    public function changes(): void
    {
        $f = Audit::changeFilters();
        [$where, $p] = Audit::changeWhere($f);
        $page = max(1, (int) Request::query('page', 1));
        $total = Audit::count('db_change_logs', 'c', $where, $p);
        $this->logView('changes', $f, $page);

        View::show('audit/changes', [
            'title'   => 'Activity log',
            'tab'     => 'changes',
            'filters' => $f,
            'rows'    => Audit::changes($f, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total'   => $total,
            'page'    => $page,
            'pages'   => max(1, (int) ceil($total / self::PER_PAGE)),
            'users'   => $this->users(),
            'tables'  => DB::run('SELECT DISTINCT table_name FROM db_change_logs ORDER BY table_name')->fetchAll(\PDO::FETCH_COLUMN),
        ]);
    }

    public function export(): void
    {
        $type = Request::query('type') === 'changes' ? 'changes' : 'activity';
        $format = Request::query('format') === 'pdf' ? 'pdf' : 'csv';
        $f = $type === 'changes' ? Audit::changeFilters() : Audit::activityFilters();
        [$where, $p] = $type === 'changes' ? Audit::changeWhere($f) : Audit::activityWhere($f);
        $total = $type === 'changes' ? Audit::count('db_change_logs', 'c', $where, $p) : Audit::count('audit_logs', 'a', $where, $p);
        $back = '/audit' . ($type === 'changes' ? '/changes' : '') . '?' . http_build_query(array_filter($f));

        if ($format === 'pdf' && $total > self::PDF_LIMIT) {
            Session::flash('warning', "That is {$total} entries. PDF exports are limited to " . self::PDF_LIMIT
                . ' rows to stay readable. Narrow the dates or filters, or export to CSV instead.');
            Response::redirect($back);
        }

        // The export is itself an audited event, recorded before any data leaves.
        AuditLogger::log('audit.exported', 'audit', $type, "Exported {$type} log as " . strtoupper($format), [
            'filters' => array_filter($f), 'rows' => $total,
        ]);

        $stamp = date('Ymd-His');
        $label = $type === 'changes' ? 'data-changes' : 'activity';
        $meta = $this->filterSummary($f) + ['Entries' => number_format($total)];

        if ($type === 'activity') {
            $headers = ['ID', 'When', 'User', 'Action', 'Entity', 'Description', 'IP address', 'Details', 'Row hash'];
            $map = static fn (array $r) => [
                $r['id'], $r['created_at'], $r['user_email'] ?? 'system', $r['action'],
                trim(($r['entity_type'] ?? '') . ' ' . ($r['entity_id'] ?? '')), $r['description'],
                $r['ip_address'], $r['metadata'] ?? '', $r['row_hash'],
            ];
            $fetch = static fn (int $limit, int $offset) => Audit::activity($f, $limit, $offset);
        } else {
            $headers = ['ID', 'When', 'User', 'Table', 'Record', 'Operation', 'Changes', 'IP address', 'Row hash'];
            $map = static fn (array $r) => [
                $r['id'], $r['created_at'], $r['email'] ?? ($r['user_agent'] ?? 'system'), $r['table_name'], $r['record_id'],
                $r['operation'], self::diffText(Audit::diff($r)), $r['ip_address'], $r['row_hash'],
            ];
            $fetch = static fn (int $limit, int $offset) => Audit::changes($f, $limit, $offset);
        }

        if ($format === 'csv') {
            // Batches keep memory flat even for very large logs.
            $rows = (static function () use ($fetch, $map): \Generator {
                for ($offset = 0; ; $offset += 1000) {
                    $batch = $fetch(1000, $offset);
                    foreach ($batch as $r) {
                        yield $map($r);
                    }
                    if (count($batch) < 1000) {
                        return;
                    }
                }
            })();
            Export::csv("ruma-audit-{$label}-{$stamp}.csv", $headers, $rows);
        }

        // PDF: drop the long hash column to keep the table readable.
        $rows = array_map(static fn ($r) => array_slice($map($r), 0, -1), $fetch(self::PDF_LIMIT, 0));
        Export::pdf("ruma-audit-{$label}-{$stamp}.pdf",
            $type === 'changes' ? 'Activity log: record changes' : 'Activity log: who did what',
            $meta, array_slice($headers, 0, -1), $rows);
    }

    public function integrity(): void
    {
        $chains = [];
        foreach (['audit_logs' => 'Activity log', 'db_change_logs' => 'Data change log'] as $table => $label) {
            $chains[$table] = [
                'label' => $label,
                'rows'  => (int) DB::value("SELECT COUNT(*) FROM {$table}"),
                'head'  => (string) DB::value('SELECT last_hash FROM audit_chain_head WHERE chain = ?', [$table]),
            ];
        }
        $last = DB::one("SELECT created_at, user_email, metadata FROM audit_logs WHERE action = 'audit.integrity_check' ORDER BY id DESC LIMIT 1");

        View::show('audit/integrity', [
            'title'  => 'Activity log',
            'tab'    => 'integrity',
            'chains' => $chains,
            'result' => Session::pull('_integrity'),
            'last'   => $last,
        ]);
    }

    public function verify(): void
    {
        @set_time_limit(300);
        $results = [HashChain::verify('audit_logs'), HashChain::verify('db_change_logs')];
        $ok = $results[0]['ok'] && $results[1]['ok'];
        AuditLogger::log('audit.integrity_check', 'audit', null,
            $ok ? 'Integrity check passed' : 'Integrity check FAILED', ['results' => $results]);
        Session::set('_integrity', $results);
        Response::redirect('/audit/integrity');
    }

    // ------------------------------------------------------------------

    /** Opening the audit log is itself recorded (first page of each search only, to avoid noise). */
    private function logView(string $type, array $f, int $page): void
    {
        if ($page === 1 && !Request::isBackground()) {
            AuditLogger::log('audit.viewed', 'audit', $type, 'Viewed the ' . ($type === 'changes' ? 'data change' : 'activity') . ' log',
                array_filter($f) ? ['filters' => array_filter($f)] : []);
        }
    }

    private function users(): array
    {
        return DB::all('SELECT id, full_name, email FROM users ORDER BY full_name');
    }

    private function filterSummary(array $f): array
    {
        $out = ['Period' => ($f['from'] ?: 'beginning') . ' to ' . ($f['to'] ?: 'now')];
        if ($f['user'] !== '') {
            $out['User'] = $f['user'] === 'system' ? 'System / direct database' : (string) DB::value('SELECT email FROM users WHERE id = ?', [(int) $f['user']]);
        }
        foreach (['action' => 'Action', 'entity' => 'Entity', 'table' => 'Table', 'op' => 'Operation', 'id' => 'Record', 'ip' => 'IP address', 'q' => 'Search'] as $k => $label) {
            if (($f[$k] ?? '') !== '') {
                $out[$label] = $f[$k];
            }
        }
        return $out;
    }

    private static function diffText(array $diff): string
    {
        $lines = [];
        foreach ($diff as $k => $v) {
            $o = is_scalar($v['old']) || $v['old'] === null ? var_export($v['old'], true) : json_encode($v['old']);
            $n = is_scalar($v['new']) || $v['new'] === null ? var_export($v['new'], true) : json_encode($v['new']);
            $lines[] = "{$k}: {$o} → {$n}";
        }
        return implode("\n", $lines);
    }
}
