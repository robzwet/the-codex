<?php

use App\Lib\Csrf;

/** @var array $campaigns */
?>
<div class="container">
    <h1 style="color:var(--red-title);font-variant:small-caps;">Your Campaigns</h1>

    <?php if (empty($campaigns)): ?>
        <p class="muted">You're not in any campaigns yet. Create one below, or join with an invite code.</p>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($campaigns as $c): ?>
                <a class="campaign-card" href="/campaign/<?= (int) $c['id'] ?>">
                    <h3><?= e($c['name']) ?></h3>
                    <div class="meta">
                        <?= (int) $c['page_count'] ?> pages ·
                        <?= $c['role'] === 'gm' ? 'GM' : 'Player' ?>
                    </div>
                    <?php if (!empty($c['description'])): ?>
                        <p class="muted" style="margin-bottom:0;"><?= e(mb_strimwidth($c['description'], 0, 90, '…')) ?></p>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="dash-actions">
        <div class="panel">
            <h3 style="margin-top:0;">Start a new campaign</h3>
            <form method="post" action="/campaigns">
                <?= Csrf::field() ?>
                <label>Name</label>
                <input type="text" name="name" placeholder="Lost Mine of Phandelver" required>
                <label>Description <span class="muted">(optional)</span></label>
                <input type="text" name="description" placeholder="A short tagline">
                <button type="submit" class="btn btn-primary" style="margin-top:14px;">Create campaign</button>
            </form>
        </div>

        <div class="panel">
            <h3 style="margin-top:0;">Join with an invite code</h3>
            <form method="post" action="/campaigns/join">
                <?= Csrf::field() ?>
                <label>Invite code</label>
                <input type="text" name="invite_code" placeholder="ABC123XYZ0" required>
                <button type="submit" class="btn" style="margin-top:14px;">Join campaign</button>
            </form>
        </div>
    </div>
</div>
