<?php

declare(strict_types=1);

namespace App\Lib;

/**
 * Per-session CSRF token. token() emits the hidden field; check() validates
 * and is called on every state-changing request.
 */
final class Csrf
{
    private static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    /** Hidden input for forms. */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    /** Raw token value (for JS/fetch headers). */
    public static function value(): string
    {
        return self::token();
    }

    /** Validate a submitted token; aborts the request on mismatch. */
    public static function check(): void
    {
        $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || !hash_equals(self::token(), $sent)) {
            http_response_code(419);
            exit('CSRF token mismatch. Please reload and try again.');
        }
    }
}
