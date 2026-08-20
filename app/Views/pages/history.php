<?php

use App\Lib\Csrf;

/** @var array $campaign @var array $tree @var array $page @var array $revisions */
$cid = (int) $campaign['id'];
$activeSlug = $page['slug'];
?>
<div class="layout">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main">
        <div class="page-toolbar">
            <a class="btn btn-sm" href="/campaign/<?= $cid ?>/page/<?= rawurlencode($page['slug']) ?>">← Back to page</a>
        </div>
        <h1 style="color:var(--gold);">History — <?= e($page['title']) ?></h1>
        <p class="muted">Every save is recorded. Restore any earlier version.</p>

        <ul class="rev-list">
            <?php foreach ($revisions as $i => $r): ?>
                <li>
                    <div>
                        <strong><?= e($r['title']) ?></strong>
                        <span class="who">by <?= e($r['username'] ?? 'unknown') ?> · <?= e($r['edited_at']) ?></span>
                        <?= $i === 0 ? '<span class="muted">(current)</span>' : '' ?>
                    </div>
                    <?php if ($i !== 0): ?>
                        <form method="post" action="/campaign/<?= $cid ?>/page/<?= rawurlencode($page['slug']) ?>/restore"
                              onsubmit="return confirm('Restore this version? The current text becomes a new revision.');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="revision_id" value="<?= (int) $r['id'] ?>">
                            <button type="submit" class="btn btn-sm">Restore</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </main>
</div>
