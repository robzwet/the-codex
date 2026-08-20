<?php

use App\Lib\Csrf;

/** @var array $campaign @var array $tree @var array $page @var string $bodyHtml @var array $meta @var array $backlinks */
$activeSlug = $page['slug'];
$cid = (int) $campaign['id'];
?>
<div class="layout">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main">
        <div class="page-toolbar">
            <a class="btn btn-sm" href="/campaign/<?= $cid ?>/page/<?= rawurlencode($page['slug']) ?>/edit">Edit</a>
            <a class="btn btn-sm" href="/campaign/<?= $cid ?>/page/<?= rawurlencode($page['slug']) ?>/history">History</a>
            <form method="post" action="/campaign/<?= $cid ?>/page/<?= rawurlencode($page['slug']) ?>/delete"
                  onsubmit="return confirm('Delete this page? This cannot be undone.');">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
        </div>

        <h1 class="page-title"><?= e($page['title']) ?></h1>
        <div class="page-title-rule">&#10022; &#10022; &#10022;</div>

        <?php if (!empty($meta)): ?>
            <div class="infobox">
                <div class="infobox-header"><?= $page['kind'] === 'note' ? 'Session' : 'Details' ?></div>
                <?php foreach ($meta as $m): ?>
                    <div class="infobox-row"><span class="k"><?= e($m['meta_key']) ?>:</span> <?= e($m['meta_value']) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="page-body"><?= $bodyHtml ?></div>

        <div class="backlinks">
            <h3>Linked from</h3>
            <?php if (empty($backlinks)): ?>
                <p class="muted">No other page links here yet.</p>
            <?php else: ?>
                <?php foreach ($backlinks as $b): ?>
                    <a href="/campaign/<?= $cid ?>/page/<?= rawurlencode($b['slug']) ?>"><?= e($b['title']) ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
