<?php
declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\AttachmentController;
use App\Controllers\AuditController;
use App\Controllers\Admin\UserController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\NotificationController;
use App\Controllers\PlaceholderController;
use App\Controllers\TicketActionController;
use App\Controllers\TicketController;

/** @var App\Core\Router $router */

// Authentication
$router->get('/login', [AuthController::class, 'showLogin'], ['guest' => true]);
$router->post('/login', [AuthController::class, 'login'], ['guest' => true]);
$router->post('/logout', [AuthController::class, 'logout'], ['allow_password_change' => true]);

// Dashboard
$router->get('/', [DashboardController::class, 'index']);

// My account
$router->get('/account', [AccountController::class, 'show'], ['allow_password_change' => true]);
$router->get('/account/password', [AccountController::class, 'showPassword'], ['allow_password_change' => true]);
$router->post('/account/password', [AccountController::class, 'updatePassword'], ['allow_password_change' => true]);

// Notifications & live updates
$router->get('/notifications', [NotificationController::class, 'index']);
$router->get('/notifications/{id}', [NotificationController::class, 'open']);
$router->post('/notifications/read-all', [NotificationController::class, 'readAll']);
$router->post('/account/notifications', [NotificationController::class, 'preferences']);
$router->get('/api/poll', [NotificationController::class, 'poll']);

// Administration: users
$router->get('/admin/users', [UserController::class, 'index'], ['can' => 'admin.users']);
$router->get('/admin/users/create', [UserController::class, 'create'], ['can' => 'admin.users']);
$router->post('/admin/users', [UserController::class, 'store'], ['can' => 'admin.users']);
$router->get('/admin/users/{id}/edit', [UserController::class, 'edit'], ['can' => 'admin.users']);
$router->post('/admin/users/{id}', [UserController::class, 'update'], ['can' => 'admin.users']);
$router->post('/admin/users/{id}/reset-password', [UserController::class, 'resetPassword'], ['can' => 'admin.users']);
$router->post('/admin/users/{id}/unlock', [UserController::class, 'unlock'], ['can' => 'admin.users']);

// Tickets
$router->get('/tickets/create', [TicketController::class, 'create'], ['can' => 'ticket.create']);
$router->post('/tickets', [TicketController::class, 'store'], ['can' => 'ticket.create']);
$router->get('/tickets', [TicketController::class, 'index'], ['can' => 'ticket.view_all']);
$router->get('/tickets/mine', [TicketController::class, 'mine'], ['can' => 'ticket.view_own']);
// Object-level checks (own ticket vs. queue access) happen in TicketPolicy.
$router->get('/tickets/{id}', [TicketController::class, 'show'], ['can' => ['ticket.view_all', 'ticket.view_own']]);
$router->get('/tickets/{id}/submitted', [TicketController::class, 'submitted'], ['can' => 'ticket.view_own']);
$router->post('/tickets/{id}/accept', [TicketActionController::class, 'accept'], ['can' => 'ticket.work']);
$router->post('/tickets/{id}/assign', [TicketActionController::class, 'assign'], ['can' => ['ticket.assign', 'ticket.reassign']]);
$router->post('/tickets/{id}/release', [TicketActionController::class, 'release'], ['can' => 'ticket.work']);
$router->post('/tickets/{id}/status', [TicketActionController::class, 'status'], ['can' => ['ticket.work', 'ticket.view_own']]);
$router->post('/tickets/{id}/priority', [TicketActionController::class, 'priority'], ['can' => 'ticket.work']);
$router->post('/tickets/{id}/comments', [TicketActionController::class, 'comment'], ['can' => ['ticket.work', 'ticket.view_own']]);
$router->get('/attachments/{id}', [AttachmentController::class, 'show'], ['can' => ['ticket.view_all', 'ticket.view_own']]);

// Audit (read-only: there are deliberately no edit or delete routes)
$router->get('/audit', [AuditController::class, 'activity'], ['can' => 'audit.view']);
$router->get('/audit/changes', [AuditController::class, 'changes'], ['can' => 'audit.view']);
$router->get('/audit/export', [AuditController::class, 'export'], ['can' => 'audit.export']);
$router->get('/audit/integrity', [AuditController::class, 'integrity'], ['can' => 'audit.verify']);
$router->post('/audit/integrity', [AuditController::class, 'verify'], ['can' => 'audit.verify']);

// Administration: settings
$router->get('/admin/settings', [SettingsController::class, 'index'], ['can' => 'admin.settings']);
$router->get('/admin/settings/categories', [SettingsController::class, 'categories'], ['can' => 'admin.settings']);
$router->post('/admin/settings/categories', [SettingsController::class, 'storeCategory'], ['can' => 'admin.settings']);
$router->post('/admin/settings/categories/{id}', [SettingsController::class, 'updateCategory'], ['can' => 'admin.settings']);
$router->get('/admin/settings/departments', [SettingsController::class, 'departments'], ['can' => 'admin.settings']);
$router->post('/admin/settings/departments', [SettingsController::class, 'storeDepartment'], ['can' => 'admin.settings']);
$router->post('/admin/settings/departments/{id}', [SettingsController::class, 'updateDepartment'], ['can' => 'admin.settings']);
$router->get('/admin/settings/priorities', [SettingsController::class, 'priorities'], ['can' => 'admin.settings']);
$router->post('/admin/settings/priorities', [SettingsController::class, 'updatePriorities'], ['can' => 'admin.settings']);

// Placeholders (replaced in later phases); permissions are already final.
$router->get('/reports', [PlaceholderController::class, 'show'], ['can' => 'report.view']);
