<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Csrf;
use App\Lib\Flash;
use App\Lib\View;

final class AuthController
{
    public static function showLogin(): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }
        View::render('auth/login', [], 'auth_layout');
    }

    public static function login(): void
    {
        Csrf::check();
        $result = Auth::login($_POST['identifier'] ?? '', $_POST['password'] ?? '');
        if ($result['ok']) {
            redirect('/dashboard');
        }
        Flash::set('error', $result['error']);
        View::render('auth/login', ['old' => $_POST], 'auth_layout');
    }

    public static function showRegister(): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }
        View::render('auth/register', [], 'auth_layout');
    }

    public static function register(): void
    {
        Csrf::check();
        $result = Auth::register(
            $_POST['username'] ?? '',
            $_POST['email'] ?? '',
            $_POST['password'] ?? ''
        );
        if ($result['ok']) {
            redirect('/dashboard');
        }
        Flash::set('error', $result['error']);
        View::render('auth/register', ['old' => $_POST], 'auth_layout');
    }

    public static function logout(): void
    {
        Csrf::check();
        Auth::logout();
        redirect('/login');
    }
}
