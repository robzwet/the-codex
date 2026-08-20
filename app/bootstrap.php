<?php
/**
 * Application bootstrap: autoloading, config, session. Included by the front
 * controller and by any CLI script that needs the app environment.
 */

declare(strict_types=1);

// Composer autoload (HTMLPurifier). Optional so CLI/migrate works pre-install.
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

// PSR-4-ish autoloader: App\Foo\Bar -> app/Foo/Bar.php
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Config available app-wide.
$GLOBALS['config'] = require __DIR__ . '/config/config.php';

// Global helper functions.
require __DIR__ . '/helpers.php';

// Session (used for auth + CSRF + flash messages).
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
