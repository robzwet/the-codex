<?php

declare(strict_types=1);

/** Escape for safe HTML output. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Asset URL with a cache-busting ?v=<mtime> so updated CSS/JS is never stale. */
function asset(string $path): string
{
    $file = dirname(__DIR__) . '/public' . $path;
    return $path . '?v=' . (is_file($file) ? filemtime($file) : time());
}

/** Redirect and stop. */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** Send a JSON response and stop. */
function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
