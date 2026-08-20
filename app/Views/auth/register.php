<?php

use App\Lib\Csrf;
use App\Lib\Flash;

$old = $old ?? [];
?>
<div class="auth-card">
    <h1>Join the Table</h1>

    <?php foreach (Flash::take() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>

    <form method="post" action="/register">
        <?= Csrf::field() ?>
        <label>Username</label>
        <input type="text" name="username" value="<?= e($old['username'] ?? '') ?>" required autofocus>
        <label>Email</label>
        <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required>
        <label>Password <span class="muted">(min 8 characters)</span></label>
        <input type="password" name="password" required>
        <button type="submit" class="btn btn-primary">Create account</button>
    </form>

    <p class="auth-alt">Already have an account? <a href="/login">Log in</a></p>
</div>
