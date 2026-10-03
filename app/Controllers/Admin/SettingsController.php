<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Lookup;
use App\Services\AuditLogger;

/** Reference data: categories, departments, priorities & SLA targets. */
final class SettingsController
{
    private const CATEGORY_ICONS = ['monitor', 'app', 'wifi', 'key', 'help', 'tag', 'file', 'building'];

    public function index(): void
    {
        Response::redirect('/admin/settings/categories');
    }

    // --- Categories --------------------------------------------------------

    public function categories(): void
    {
        View::show('admin/settings/categories', [
            'title'         => 'Settings',
            'tab'           => 'categories',
            'categories'    => Lookup::categories(false),
            'subcategories' => Lookup::subcategories(false),
            'icons'         => self::CATEGORY_ICONS,
            'usage'         => array_column(DB::all(
                'SELECT category_id AS id, COUNT(*) n FROM tickets GROUP BY category_id
                 UNION ALL SELECT subcategory_id, COUNT(*) FROM tickets WHERE subcategory_id IS NOT NULL GROUP BY subcategory_id'
            ), 'n', 'id'),
        ]);
    }

    public function storeCategory(): void
    {
        $data = $this->categoryInput();
        $id = DB::insert('INSERT INTO categories (parent_id, name, description, icon, sort_order) VALUES (?, ?, ?, ?, ?)',
            [$data['parent_id'], $data['name'], $data['description'], $data['icon'], $data['sort_order']]);
        AuditLogger::log('settings.category_created', 'category', $id, "Added category {$data['name']}", $data);
        Session::flash('success', "\"{$data['name']}\" added.");
        Response::redirect('/admin/settings/categories');
    }

    public function updateCategory(int $id): void
    {
        $before = DB::one('SELECT * FROM categories WHERE id = ?', [$id]) ?? throw new HttpException(404);
        $data = $this->categoryInput($before);
        DB::run('UPDATE categories SET name = ?, description = ?, icon = ?, sort_order = ?, is_active = ? WHERE id = ?',
            [$data['name'], $data['description'], $data['icon'], $data['sort_order'], $data['is_active'], $id]);
        AuditLogger::log('settings.category_updated', 'category', $id, "Updated category {$data['name']}",
            ['changes' => $this->diff($before, $data)]);
        Session::flash('success', 'Category saved.');
        Response::redirect('/admin/settings/categories');
    }

    private function categoryInput(?array $existing = null): array
    {
        $in = Request::only(['parent_id', 'name', 'description', 'icon', 'sort_order']);
        $parentId = $existing ? $existing['parent_id'] : ($in['parent_id'] !== '' ? (int) $in['parent_id'] : null);

        $v = Validator::make($in, ['name' => 'required|max:100', 'description' => 'max:255', 'sort_order' => 'int']);
        if ($parentId !== null && !DB::value('SELECT 1 FROM categories WHERE id = ? AND parent_id IS NULL', [$parentId])) {
            $v->addError('name', 'The parent category no longer exists.');
        }
        $dupe = DB::value('SELECT id FROM categories WHERE name = ? AND parent_id <=> ? AND id <> ?',
            [$in['name'], $parentId, $existing['id'] ?? 0]);
        if ($dupe) {
            $v->addError('name', 'There is already a category with this name here.');
        }
        if ($v->fails()) {
            Session::flash('danger', implode(' ', $v->errors()));
            Response::redirect('/admin/settings/categories');
        }
        return [
            'parent_id'   => $parentId,
            'name'        => $in['name'],
            'description' => $in['description'],
            'icon'        => in_array($in['icon'], self::CATEGORY_ICONS, true) ? $in['icon'] : ($existing['icon'] ?? 'tag'),
            'sort_order'  => (int) $in['sort_order'],
            'is_active'   => $existing ? (isset($_POST['is_active']) ? 1 : 0) : 1,
        ];
    }

    // --- Departments -------------------------------------------------------

    public function departments(): void
    {
        View::show('admin/settings/departments', [
            'title'       => 'Settings',
            'tab'         => 'departments',
            'departments' => Lookup::departments(false),
            'usage'       => array_column(DB::all(
                'SELECT department_id AS id, COUNT(*) n FROM users WHERE department_id IS NOT NULL GROUP BY department_id'
            ), 'n', 'id'),
        ]);
    }

    public function storeDepartment(): void
    {
        $data = $this->departmentInput();
        $id = DB::insert('INSERT INTO departments (name, code) VALUES (?, ?)', [$data['name'], $data['code']]);
        AuditLogger::log('settings.department_created', 'department', $id, "Added department {$data['name']}", $data);
        Session::flash('success', "\"{$data['name']}\" added.");
        Response::redirect('/admin/settings/departments');
    }

    public function updateDepartment(int $id): void
    {
        $before = DB::one('SELECT * FROM departments WHERE id = ?', [$id]) ?? throw new HttpException(404);
        $data = $this->departmentInput($id);
        $data['is_active'] = isset($_POST['is_active']) ? 1 : 0;
        DB::run('UPDATE departments SET name = ?, code = ?, is_active = ? WHERE id = ?',
            [$data['name'], $data['code'], $data['is_active'], $id]);
        AuditLogger::log('settings.department_updated', 'department', $id, "Updated department {$data['name']}",
            ['changes' => $this->diff($before, $data)]);
        Session::flash('success', 'Department saved.');
        Response::redirect('/admin/settings/departments');
    }

    private function departmentInput(int $ignoreId = 0): array
    {
        $in = Request::only(['name', 'code']);
        $in['code'] = strtoupper($in['code']);
        $v = Validator::make($in, ['name' => 'required|max:100', 'code' => 'required|max:20'], ['code' => 'Short code']);
        if (!preg_match('/^[A-Z0-9_-]*$/', $in['code'])) {
            $v->addError('code', 'Short code can use letters, numbers, - and _ only.');
        }
        if (DB::value('SELECT 1 FROM departments WHERE (name = ? OR code = ?) AND id <> ?', [$in['name'], $in['code'], $ignoreId])) {
            $v->addError('name', 'Another department already uses this name or code.');
        }
        if ($v->fails()) {
            Session::flash('danger', implode(' ', $v->errors()));
            Response::redirect('/admin/settings/departments');
        }
        return $in;
    }

    // --- Priorities & SLA --------------------------------------------------

    public function priorities(): void
    {
        View::show('admin/settings/priorities', [
            'title'      => 'Settings',
            'tab'        => 'priorities',
            'priorities' => Lookup::priorities(),
        ]);
    }

    public function updatePriorities(): void
    {
        $rows = $_POST['p'] ?? [];
        if (!is_array($rows)) {
            throw new HttpException(422);
        }
        $before = array_column(Lookup::priorities(), null, 'id');
        $errors = [];
        $clean = [];

        foreach ($before as $id => $old) {
            $r = $rows[$id] ?? [];
            $name = trim((string) ($r['name'] ?? ''));
            $response = (int) ($r['response_minutes'] ?? 0);
            $resolutionHours = (float) ($r['resolution_hours'] ?? 0);
            $resolution = (int) round($resolutionHours * 60);
            $warn = (int) ($r['warn_percent'] ?? 80);

            if ($name === '' || mb_strlen($name) > 40) {
                $errors[] = 'Each priority needs a name (up to 40 characters).';
            }
            if ($response < 1 || $response > 10080) {
                $errors[] = "{$old['name']}: response target must be between 1 minute and 7 days.";
            }
            if ($resolution < $response || $resolution > 60 * 24 * 60) {
                $errors[] = "{$old['name']}: resolution target must be at least the response target and at most 60 days.";
            }
            if ($warn < 50 || $warn > 95) {
                $errors[] = "{$old['name']}: warning point must be between 50% and 95%.";
            }
            $clean[$id] = [
                'name' => $name, 'description' => mb_substr(trim((string) ($r['description'] ?? '')), 0, 255),
                'response_minutes' => $response, 'resolution_minutes' => $resolution, 'warn_percent' => $warn,
            ];
        }
        if ($errors) {
            Session::flash('danger', implode(' ', array_unique($errors)));
            Response::redirect('/admin/settings/priorities');
        }

        DB::transaction(function () use ($clean, $before): void {
            foreach ($clean as $id => $c) {
                DB::run('UPDATE priorities SET name = ?, description = ? WHERE id = ?', [$c['name'], $c['description'], $id]);
                DB::run('INSERT INTO sla_policies (priority_id, response_minutes, resolution_minutes, warn_percent) VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE response_minutes = VALUES(response_minutes),
                             resolution_minutes = VALUES(resolution_minutes), warn_percent = VALUES(warn_percent)',
                    [$id, $c['response_minutes'], $c['resolution_minutes'], $c['warn_percent']]);
                $changes = $this->diff($before[$id], $c);
                if ($changes) {
                    AuditLogger::log('settings.sla_updated', 'priority', $id, "Updated priority/SLA {$c['name']}", ['changes' => $changes]);
                }
            }
        });
        Session::flash('success', 'Priorities and SLA targets saved. They apply to new tickets and to tickets whose priority changes.');
        Response::redirect('/admin/settings/priorities');
    }

    private function diff(array $before, array $after): array
    {
        $out = [];
        foreach ($after as $k => $v) {
            if (array_key_exists($k, $before) && (string) $before[$k] !== (string) $v) {
                $out[$k] = ['from' => $before[$k], 'to' => $v];
            }
        }
        return $out;
    }
}
