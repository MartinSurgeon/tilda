<?php
declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\Admin\UserController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\PlaceholderController;

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

// Administration: users
$router->get('/admin/users', [UserController::class, 'index'], ['can' => 'admin.users']);
$router->get('/admin/users/create', [UserController::class, 'create'], ['can' => 'admin.users']);
$router->post('/admin/users', [UserController::class, 'store'], ['can' => 'admin.users']);
$router->get('/admin/users/{id}/edit', [UserController::class, 'edit'], ['can' => 'admin.users']);
$router->post('/admin/users/{id}', [UserController::class, 'update'], ['can' => 'admin.users']);
$router->post('/admin/users/{id}/reset-password', [UserController::class, 'resetPassword'], ['can' => 'admin.users']);
$router->post('/admin/users/{id}/unlock', [UserController::class, 'unlock'], ['can' => 'admin.users']);

// Placeholders (replaced in later phases) — permissions are already final.
$router->get('/tickets/create', [PlaceholderController::class, 'show'], ['can' => 'ticket.create']);
$router->get('/tickets', [PlaceholderController::class, 'show'], ['can' => 'ticket.view_all']);
$router->get('/tickets/mine', [PlaceholderController::class, 'show'], ['can' => 'ticket.view_own']);
$router->get('/notifications', [PlaceholderController::class, 'show']);
$router->get('/audit', [PlaceholderController::class, 'show'], ['can' => 'audit.view']);
$router->get('/reports', [PlaceholderController::class, 'show'], ['can' => 'report.view']);
$router->get('/admin/settings', [PlaceholderController::class, 'show'], ['can' => 'admin.settings']);
