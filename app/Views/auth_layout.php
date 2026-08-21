<?php /** @var string $content */ ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($GLOBALS['config']['app']['name']) ?></title>
    <link rel="stylesheet" href="<?= asset('/assets/css/app.css') ?>">
</head>
<body>
    <div class="auth-wrap">
        <?= $content ?>
    </div>
</body>
</html>
