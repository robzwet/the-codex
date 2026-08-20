<?php

use App\Lib\Csrf;

/** @var array $campaign @var array $tree @var array $categories @var string $mode @var array $page @var array $meta */
$cid = (int) $campaign['id'];
$activeSlug = $page['slug'] ?? null;
$isEdit = $mode === 'edit';
$action = $isEdit
    ? '/campaign/' . $cid . '/page/' . rawurlencode($page['slug']) . '/edit'
    : '/campaign/' . $cid . '/pages';

// Group categories for an indented <select>.
$byParent = [];
foreach ($categories as $c) {
    $byParent[$c['parent_id'] ?? 0][] = $c;
}
$renderOptions = function (int $parent, int $depth) use (&$renderOptions, $byParent, $page): string {
    $out = '';
    foreach ($byParent[$parent] ?? [] as $c) {
        $sel = ((string) ($page['category_id'] ?? '') === (string) $c['id']) ? ' selected' : '';
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

        <form method="post" action="<?= e($action) ?>" id="page-form" data-search-url="/api/campaign/<?= $cid ?>/search">
            <?= Csrf::field() ?>

            <label>Title</label>
            <input type="text" name="title" id="title" value="<?= e($page['title'] ?? '') ?>" required autofocus>

            <div style="display:flex;gap:16px;">
                <div style="flex:1;">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">— Uncategorised —</option>
                        <?= $renderOptions(0, 0) ?>
                    </select>
                </div>
                <div style="flex:1;">
                    <label>Type</label>
                    <select name="kind">
                        <option value="entity" <?= ($page['kind'] ?? 'entity') === 'entity' ? 'selected' : '' ?>>Entity (NPC, place, item…)</option>
                        <option value="note" <?= ($page['kind'] ?? '') === 'note' ? 'selected' : '' ?>>Session note</option>
                    </select>
                </div>
            </div>

            <label>Infobox fields <span class="muted">(the key/value panel, e.g. Race → Human)</span></label>
            <div id="meta-rows">
                <?php foreach ($meta as $m): ?>
                    <div class="meta-row">
                        <input type="text" name="meta_key[]" placeholder="Field" value="<?= e($m['meta_key']) ?>">
                        <input type="text" name="meta_value[]" placeholder="Value" value="<?= e($m['meta_value']) ?>">
                        <button type="button" class="btn btn-sm meta-remove">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm" id="add-meta">+ Add field</button>

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

            <div style="margin-top:18px;display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create page' ?></button>
                <a class="btn" href="<?= $isEdit
                    ? '/campaign/' . $cid . '/page/' . rawurlencode($page['slug'])
                    : '/campaign/' . $cid ?>">Cancel</a>
            </div>
        </form>
    </main>
</div>
