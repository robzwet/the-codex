<?php

declare(strict_types=1);

namespace App\Lib;

/**
 * Authentication + current-user helpers. Passwords are hashed with
 * password_hash(); auth state lives in the session.
 */
final class Auth
{
    public static function register(string $username, string $email, string $password): array
    {
        $username = trim($username);
        $email = trim($email);

        if (strlen($username) < 3) {
            return ['ok' => false, 'error' => 'Username must be at least 3 characters.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Please enter a valid email address.'];
        }
        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
        }

        $exists = Db::run(
            'SELECT id FROM users WHERE username = ? OR email = ?',
            [$username, $email]
        )->fetch();
        if ($exists) {
            return ['ok' => false, 'error' => 'That username or email is already taken.'];
        }

        Db::run(
            'INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)',
            [$username, $email, password_hash($password, PASSWORD_DEFAULT)]
        );

        $id = (int) Db::conn()->lastInsertId();
        self::establishSession($id, $username);
        return ['ok' => true, 'id' => $id];
    }

    public static function login(string $usernameOrEmail, string $password): array
    {
        $usernameOrEmail = trim($usernameOrEmail);
        $user = Db::run(
            'SELECT * FROM users WHERE username = ? OR email = ?',
            [$usernameOrEmail, $usernameOrEmail]
        )->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Incorrect username or password.'];
        }

        self::establishSession((int) $user['id'], $user['username']);
        return ['ok' => true, 'id' => (int) $user['id']];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function username(): ?string
    {
        return $_SESSION['username'] ?? null;
    }

    /** Redirect to login if not authenticated. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    private static function establishSession(int $id, string $username): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['username'] = $username;
    }
}
