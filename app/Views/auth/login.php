<?php

use App\Lib\Csrf;
use App\Lib\Flash;

$old = $old ?? [];
?>
<div class="auth-card">
    <h1>The Codex</h1>
    <p class="muted" style="text-align:center;margin-top:-8px;">Your party's shared chronicle.</p>

    <?php foreach (Flash::take() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>

    <form method="post" action="/login">
        <?= Csrf::field() ?>
        <label>Username or email</label>
        <input type="text" name="identifier" value="<?= e($old['identifier'] ?? '') ?>" required autofocus>
        <label>Password</label>
        <input type="password" name="password" required>
        <button type="submit" class="btn btn-primary">Log in</button>
    </form>

    <p class="auth-alt">New here? <a href="/register">Create an account</a></p>
</div>
