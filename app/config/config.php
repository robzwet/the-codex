<?php
/**
 * Central configuration, driven entirely by environment variables so the same
 * image runs anywhere. Defaults are dev-friendly; production values come from .env.
 */
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'db',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'codex',
        'user' => getenv('DB_USER') ?: 'codex',
        'pass' => getenv('DB_PASS') ?: 'codex',
    ],
    'app' => [
        'name'   => 'The Codex',
        'secret' => getenv('APP_SECRET') ?: 'insecure-dev-secret',
    ],
];
