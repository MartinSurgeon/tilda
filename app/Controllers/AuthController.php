<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

final class AuthController
{
    public function showLogin(): void
    {
        View::show('auth/login', ['title' => 'Sign in'], 'auth');
    }

    public function login(): void
    {
        $email = (string) Request::input('email', '');
        $password = (string) ($_POST['password'] ?? ''); // not trimmed: spaces are valid password characters

        if ($email === '' || $password === '') {
            back_with_errors([
                'email'    => $email === '' ? 'Please enter your work email.' : null,
                'password' => $password === '' ? 'Please enter your password.' : null,
            ], '/login', ['email' => $email]);
        }

        $result = Auth::attempt($email, $password);

        if (!$result['ok']) {
            $message = match ($result['reason']) {
                'locked', 'throttled' => 'Too many sign-in attempts. Please wait ' . config('login.lock_minutes')
                    . ' minutes and try again, or contact the IT help desk.',
                default => 'That email and password do not match. Check them and try again.',
            };
            back_with_errors(['form' => $message], '/login', ['email' => $email]);
        }

        Auth::login($result['user']);

        if ((int) $result['user']['must_change_password'] === 1) {
            Session::flash('info', 'Welcome! Before you continue, please choose your own password.');
            Response::redirect('/account/password');
        }

        Response::redirect(safe_path(Session::pull('intended', '/')));
    }

    public function logout(): void
    {
        Auth::logout();
        Session::start();
        Session::flash('success', 'You have signed out.');
        Response::redirect('/login');
    }
}
