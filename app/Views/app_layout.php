<?php

use App\Lib\Auth;
use App\Lib\Csrf;
use App\Lib\Flash;

/** @var string $content */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($GLOBALS['config']['app']['name']) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <meta name="csrf-token" content="<?= e(Csrf::value()) ?>">
</head>
<body>
    <div class="topbar">
        <a href="/dashboard" class="brand">The Codex</a>
        <span class="spacer"></span>
        <span class="user"><?= e(Auth::username()) ?></span>
        <form method="post" action="/logout">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-sm">Log out</button>
        </form>
    </div>

    <?php $flashes = Flash::take(); ?>
    <?php if ($flashes): ?>
        <div class="container" style="margin-bottom:0;">
            <?php foreach ($flashes as $f): ?>
                <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= $content ?>

    <script src="/assets/js/app.js"></script>
</body>
</html>
