<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\View;

/**
 * Temporary: lets the full navigation be reviewed in Phase 1. Each route
 * already enforces its final permission. Replaced phase by phase.
 */
final class PlaceholderController
{
    private const PAGES = [
        '/notifications'  => ['Notifications', 'bell', 3],
        '/audit'          => ['Audit log', 'shield-check', 4],
        '/reports'        => ['Reports', 'chart', 5],
    ];

    public function show(): void
    {
        [$title, $icon, $phase] = self::PAGES[Request::path()] ?? ['Coming soon', 'clock', 0];
        View::show('placeholder', compact('title', 'icon', 'phase'));
    }
}
