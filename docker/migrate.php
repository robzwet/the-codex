<?php
/**
 * Startup migration runner.
 *
 * 1. Waits (with backoff) for the MySQL container to accept connections.
 * 2. Executes db/schema.sql. All statements use CREATE TABLE IF NOT EXISTS,
 *    so running this on every boot is safe and idempotent.
 */

$config = require __DIR__ . '/../app/config/config.php';
$db = $config['db'];

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);

$pdo = null;
$maxAttempts = 30;
for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    try {
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        break;
    } catch (PDOException $e) {
        fwrite(STDERR, "[migrate] waiting for database ($attempt/$maxAttempts): {$e->getMessage()}\n");
        sleep(2);
    }
}

if (!$pdo) {
    fwrite(STDERR, "[migrate] database not reachable, giving up.\n");
    exit(1);
}

$schema = file_get_contents(__DIR__ . '/../db/schema.sql');
if ($schema === false) {
    fwrite(STDERR, "[migrate] could not read schema.sql\n");
    exit(1);
}

// Strip full-line SQL comments so they don't get bundled into a statement,
// then split on statement-terminating semicolons. Our schema has no stored
// routines, so this simple split is sufficient.
$schema = preg_replace('/^\s*--.*$/m', '', $schema);
$statements = array_filter(
    array_map('trim', preg_split('/;\s*[\r\n]/', $schema)),
    fn($s) => $s !== ''
);

foreach ($statements as $sql) {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        fwrite(STDERR, "[migrate] statement failed: {$e->getMessage()}\n");
        fwrite(STDERR, substr($sql, 0, 120) . "...\n");
        exit(1);
    }
}

fwrite(STDOUT, "[migrate] schema applied successfully.\n");
