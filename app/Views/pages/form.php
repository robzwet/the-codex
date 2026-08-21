<?php

use App\Lib\Csrf;
use App\Lib\View;

/** @var array $campaign @var array $tree @var array $categories @var string $mode
 * @var array $page @var array $fields @var array $values @var int $campaignId */
$cid = (int) $campaign['id'];
$activeSlug = $page['slug'] ?? null;
$isEdit = $mode === 'edit';
$action = $isEdit
    ? '/campaign/' . $cid . '/page/' . rawurlencode($page['slug']) . '/edit'
    : '/campaign/' . $cid . '/pages';
$currentCat = $page['category_id'] ?? null;

$byParent = [];
foreach ($categories as $c) {
    $byParent[$c['parent_id'] ?? 0][] = $c;
}
$renderOptions = function (int $parent, int $depth) use (&$renderOptions, $byParent, $currentCat): string {
    $out = '';
    foreach ($byParent[$parent] ?? [] as $c) {
        $sel = ((string) ($currentCat ?? '') === (string) $c['id']) ? ' selected' : '';
        $out .= '<option value="' . (int) $c['id'] . '"' . $sel . '>'
              . str_repeat('— ', $depth) . e($c['name']) . '</option>';
        $out .= $renderOptions((int) $c['id'], $depth + 1);
    }
    return $out;
};
?>
<div class="layout">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main">
        <h1 style="color:var(--gold);"><?= $isEdit ? 'Edit page' : 'New page' ?></h1>

        <form method="post" action="<?= e($action) ?>" id="page-form" enctype="multipart/form-data"
              data-search-url="/api/campaign/<?= $cid ?>/search"
              data-fields-url="/api/campaign/<?= $cid ?>/fields"
              data-page-id="<?= $isEdit ? (int) $page['id'] : '' ?>">
            <?= Csrf::field() ?>

            <label>Title</label>
            <input type="text" name="title" id="title" value="<?= e($page['title'] ?? '') ?>" required autofocus>

            <label>Category</label>
            <select name="category_id" id="category-select">
                <option value="">— Uncategorised —</option>
                <?= $renderOptions(0, 0) ?>
            </select>
            <p style="margin:6px 0 0;">
                <a id="manage-fields-link" class="btn-link" style="font-size:13px;"
                   href="<?= $currentCat ? '/campaign/' . $cid . '/category/' . (int) $currentCat . '/fields' : '#' ?>"
                   <?= $currentCat ? '' : 'hidden' ?>>⚙ Manage fields for this category</a>
            </p>

            <fieldset class="template-fields">
                <legend>Details</legend>
                <div id="fields-section">
                    <?= View::capture('partials/fields', ['fields' => $fields, 'values' => $values, 'campaignId' => $campaignId, 'scaffold' => $scaffold ?? '']) ?>
                </div>
            </fieldset>

            <label style="margin-top:18px;">Body <span class="muted">— type <code>[[</code> to link another page</span></label>
            <div class="editor-toolbar" id="toolbar">
                <button type="button" data-cmd="bold"><b>B</b></button>
                <button type="button" data-cmd="italic"><i>I</i></button>
                <button type="button" data-cmd="underline"><u>U</u></button>
                <button type="button" data-block="h2">H2</button>
                <button type="button" data-block="h3">H3</button>
                <button type="button" data-block="blockquote">&ldquo;</button>
                <button type="button" data-cmd="insertUnorderedList">• List</button>
                <button type="button" data-cmd="insertOrderedList">1. List</button>
                <button type="button" data-wikilink>[[ Link ]]</button>
            </div>
            <div class="editor" id="editor" contenteditable="true"
                 data-placeholder="Write your notes here…"><?= $page['body_html'] ?? '' ?></div>

            <input type="hidden" name="body_html" id="body_html">

            <label style="margin-top:18px;">Tags <span class="muted">— comma-separated, e.g. <code>quest/open-thread, villain</code></span></label>
            <input type="text" name="tags" value="<?= e($tags ?? '') ?>" list="all-tags" placeholder="add tags…">
            <datalist id="all-tags">
                <?php foreach (($allTags ?? []) as $t): ?><option value="<?= e($t) ?>"></option><?php endforeach; ?>
            </datalist>

            <div style="margin-top:18px;display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create page' ?></button>
                <a class="btn" href="<?= $isEdit
                    ? '/campaign/' . $cid . '/page/' . rawurlencode($page['slug'])
                    : '/campaign/' . $cid ?>">Cancel</a>
            </div>
        </form>
    </main>
</div>
