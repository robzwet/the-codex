<?php
/** @var array $campaign @var array $tree @var array $allTags @var string $tag @var array $pages */
$cid = (int) $campaign['id'];
?>
<div class="layout">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main">
        <h1 style="color:var(--gold);">Tags</h1>

        <?php if (empty($allTags)): ?>
            <p class="muted">No tags yet. Add comma-separated tags when editing a page (e.g. <code>quest/open-thread</code>).</p>
        <?php else: ?>
            <div class="tag-cloud">
                <?php foreach ($allTags as $t): ?>
                    <a class="tag <?= $tag === $t['tag'] ? 'tag--active' : '' ?>"
                       href="/campaign/<?= $cid ?>/tags?tag=<?= rawurlencode($t['tag']) ?>">
                        #<?= e($t['tag']) ?> <span class="tag-count"><?= (int) $t['n'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($tag !== ''): ?>
            <h2 style="margin-top:24px;">Pages tagged <span class="tag">#<?= e($tag) ?></span></h2>
            <?php if (empty($pages)): ?>
                <p class="muted">Nothing here.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($pages as $p): ?>
                        <li><a href="/campaign/<?= $cid ?>/page/<?= rawurlencode($p['slug']) ?>"><?= e($p['title']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
