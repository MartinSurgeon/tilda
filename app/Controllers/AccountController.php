<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\PasswordPolicy;

final class AccountController
{
    public function show(): void
    {
        View::show('account/show', ['title' => 'My account']);
    }

    public function showPassword(): void
    {
        View::show('account/password', [
            'title'  => 'Change password',
            'forced' => (int) Auth::user()['must_change_password'] === 1,
            'rules'  => PasswordPolicy::rulesText(),
        ]);
    }

    public function updatePassword(): void
    {
        $user = Auth::user();
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');

        $hash = (string) DB::value('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        $errors = [];

        if (!password_verify($current, $hash)) {
            $errors['current_password'] = 'That is not your current password.';
        }
        $problems = PasswordPolicy::check($new, $user);
        if ($problems) {
            $errors['password'] = implode(' ', $problems);
        } elseif (password_verify($new, $hash)) {
            $errors['password'] = 'Your new password must be different from your current one.';
        }
        if (!isset($errors['password']) && !hash_equals($new, $confirm)) {
            $errors['password_confirmation'] = 'The two passwords do not match.';
        }

        if ($errors) {
            AuditLogger::log('account.password_change_failed', 'user', $user['id'], 'Password change rejected', [
                'fields' => array_keys($errors),
            ]);
            back_with_errors($errors, '/account/password');
        }

        DB::run(
            'UPDATE users SET password_hash = ?, must_change_password = 0, password_changed_at = NOW() WHERE id = ?',
            [password_hash($new, PASSWORD_DEFAULT, ['cost' => 12]), $user['id']]
        );
        AuditLogger::log('account.password_changed', 'user', $user['id'], 'Password changed', [
            'was_forced' => (int) $user['must_change_password'] === 1,
        ]);

        Session::regenerate();
        Csrf::rotate();
        Auth::refresh();
        Session::flash('success', 'Your password has been changed.');
        Response::redirect('/');
    }
}
