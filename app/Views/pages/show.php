<?php

use App\Lib\Csrf;

/** @var array $campaign @var array $tree @var array $page @var string $bodyHtml
 * @var array $display @var array $leftover @var array $authors @var bool $isSession @var array $backlinks */
$activeSlug = $page['slug'];
$cid = (int) $campaign['id'];

// Pull out an image field (if any) to show at the top of the infobox.
$image = null;
$rows = [];
foreach ($display as $d) {
    if ($d['type'] === 'image' && $image === null) {
        $image = $d['value'];
    } else {
        $rows[] = $d;
    }
}
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

        <?php if (!empty($isSession) && (($neighbors['prev'] ?? null) || ($neighbors['next'] ?? null))): ?>
            <div class="session-nav">
                <?php if ($neighbors['prev']): ?>
                    <a href="/campaign/<?= $cid ?>/page/<?= rawurlencode($neighbors['prev']['slug']) ?>">← <?= e($neighbors['prev']['title']) ?></a>
                <?php else: ?><span></span><?php endif; ?>
                <?php if ($neighbors['next']): ?>
                    <a href="/campaign/<?= $cid ?>/page/<?= rawurlencode($neighbors['next']['slug']) ?>"><?= e($neighbors['next']['title']) ?> →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <h1 class="page-title"><?= e($page['title']) ?></h1>
        <div class="page-title-rule">&#10022; &#10022; &#10022;</div>
        <p class="byline muted">
            <?php if (!empty($authors['created_by'])): ?>Written by <strong><?= e($authors['created_by']) ?></strong><?php endif; ?>
            <?php if (!empty($authors['updated_by']) && $authors['updated_by'] !== $authors['created_by']): ?>
                · last edited by <strong><?= e($authors['updated_by']) ?></strong><?php endif; ?>
        </p>

        <?php if ($image || $rows || $leftover): ?>
            <div class="infobox infobox--float">
                <div class="infobox-header"><?= $isSession ? 'Session' : 'Details' ?></div>
                <?php if ($image): ?>
                    <div class="infobox-image"><img src="<?= e($image) ?>" alt="<?= e($page['title']) ?>"></div>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <div class="infobox-row">
                        <span class="k"><?= e($r['label']) ?>:</span>
                        <?php if ($r['type'] === 'multi'): ?>
                            <?= e(implode(', ', array_map('trim', explode(',', $r['value'])))) ?>
                        <?php elseif ($r['type'] === 'link'): ?>
                            <a class="wikilink<?= empty($r['exists']) ? ' wikilink--new' : '' ?>" href="<?= e($r['href']) ?>"><?= e($r['value']) ?></a>
                        <?php else: ?>
                            <?= e($r['value']) ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($leftover as $r): ?>
                    <div class="infobox-row"><span class="k"><?= e($r['label']) ?>:</span> <?= e($r['value']) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($related)): ?>
            <div class="related">
                <?php foreach ($related as $g): ?>
                    <div class="related-group">
                        <div class="related-head">
                            <?php if ($g['icon'] !== ''): ?><span class="related-icon"><?= e($g['icon']) ?></span> <?php endif; ?><?= e($g['category']) ?>
                        </div>
                        <ul class="related-list">
                            <?php foreach ($g['items'] as $it): ?>
                                <li>
                                    <a href="/campaign/<?= $cid ?>/page/<?= rawurlencode($it['slug']) ?>"><?= e($it['title']) ?></a>
                                    <?php if (!empty($it['field'])): ?><span class="related-field"><?= e($it['field']) ?></span><?php endif; ?>
                                    <?php if ($it['info'] !== ''): ?><span class="related-info"><?= e($it['info']) ?></span><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($sections)): ?>
            <div class="page-sections">
                <?php foreach ($sections as $sec): ?>
                    <?php $hasContent = trim(strip_tags($sec['html'], '<img><ul><ol>')) !== '' || strpos($sec['html'], '<img') !== false; ?>
                    <details class="page-section"<?= $hasContent ? ' open' : '' ?>>
                        <summary><?= e($sec['title']) ?></summary>
                        <div class="page-body"><?= $sec['html'] ?></div>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="page-body"><?= $bodyHtml ?></div>
        <?php endif; ?>

        <?php if (!empty($tags)): ?>
            <div class="tag-list">
                <?php foreach ($tags as $t): ?>
                    <a class="tag" href="/campaign/<?= $cid ?>/tags?tag=<?= rawurlencode($t) ?>">#<?= e($t) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

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
