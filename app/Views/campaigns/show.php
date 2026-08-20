<?php
/** @var array $campaign @var array $tree @var array $members @var bool $hasPages @var ?array $firstPage */
?>
<div class="layout">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main">
        <h1 class="page-title"><?= e($campaign['name']) ?></h1>
        <div class="page-title-rule">&#10022; &#10022; &#10022;</div>

        <?php if (!empty($campaign['description'])): ?>
            <p class="muted" style="text-align:center;"><?= e($campaign['description']) ?></p>
        <?php endif; ?>

        <div class="infobox">
            <div class="infobox-header">Campaign</div>
            <div class="infobox-row"><span class="k">Invite code:</span>
                <span class="invite-code"><?= e($campaign['invite_code']) ?></span>
                <span class="muted">— share this so others can join.</span>
            </div>
            <div class="infobox-row"><span class="k">Members:</span>
                <?= implode(', ', array_map(fn($m) => e($m['username']) . ($m['role'] === 'gm' ? ' (GM)' : ''), $members)) ?>
            </div>
        </div>

        <?php if (!$hasPages): ?>
            <div class="empty-state">
                <h2>An empty tome awaits.</h2>
                <p>Write your first session note or NPC page. Link things with <code>[[double brackets]]</code>.</p>
                <a class="btn btn-primary" href="/campaign/<?= (int) $campaign['id'] ?>/new">+ Create the first page</a>
            </div>
        <?php else: ?>
            <p>Pick a page from the sidebar, or
                <a href="/campaign/<?= (int) $campaign['id'] ?>/page/<?= rawurlencode($firstPage['slug']) ?>">open “<?= e($firstPage['title']) ?>”</a>.
            </p>
            <a class="btn btn-primary" href="/campaign/<?= (int) $campaign['id'] ?>/new">+ New page</a>
        <?php endif; ?>
    </main>
</div>
