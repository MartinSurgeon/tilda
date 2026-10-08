<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;

/**
 * Navigation, grouped (max 2 levels, ≤ 7 items per group). Items appear only
 * when the user holds the permission — the routes enforce it again server-side.
 */
function nav_groups(): array
{
    $groups = [
        'Work' => [
            ['label' => 'Dashboard',     'path' => '/',               'icon' => 'dashboard', 'show' => true],
            ['label' => 'Ticket queue',  'path' => '/tickets',        'icon' => 'inbox',     'show' => can('ticket.view_all')],
            ['label' => 'My tickets',    'path' => '/tickets/mine',   'icon' => 'ticket',    'show' => can('ticket.view_own')],
            ['label' => 'Notifications', 'path' => '/notifications',  'icon' => 'bell',      'show' => true],
        ],
        'Insights' => [
            ['label' => 'Reports',   'path' => '/reports', 'icon' => 'chart',        'show' => can('report.view')],
            ['label' => 'Activity log', 'path' => '/audit',   'icon' => 'shield-check', 'show' => can('audit.view')],
        ],
        'Administration' => [
            ['label' => 'Users',    'path' => '/admin/users',    'icon' => 'users',    'show' => can('admin.users')],
            ['label' => 'Settings', 'path' => '/admin/settings', 'icon' => 'settings', 'show' => can('admin.settings')],
        ],
    ];

    foreach ($groups as $name => $items) {
        $groups[$name] = array_values(array_filter($items, static fn ($i) => $i['show']));
        if ($groups[$name] === []) {
            unset($groups[$name]);
        }
    }
    return $groups;
}

/** Up to two primary destinations for the mobile bottom bar (besides New + Alerts + More). */
function bottom_nav_items(): array
{
    $flat = array_merge(...array_values(nav_groups()));
    $flat = array_filter($flat, static fn ($i) => $i['path'] !== '/notifications');
    return array_slice(array_values($flat), 0, 2);
}

function nav_is_active(string $path): bool
{
    $current = Request::path();
    if ($path === '/') {
        return $current === '/';
    }
    // Most specific match wins: /tickets must not light up on /tickets/mine.
    if ($path === '/tickets') {
        return $current === '/tickets' || (bool) preg_match('#^/tickets/\d+#', $current);
    }
    return $current === $path || str_starts_with($current, $path . '/');
}

function unread_notifications(): int
{
    static $count = null;
    return $count ??= (int) DB::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [Auth::id()]);
}

/** Should this browser chime for new notifications? Unset = on for IT staff, off for everyone else. */
function notification_sound_on(): bool
{
    $user = user();
    if (!$user) {
        return false;
    }
    return $user['notification_sound'] === null ? can('ticket.work') : (int) $user['notification_sound'] === 1;
}
