<?php

use App\Lib\Csrf;

/** @var array $campaign @var array $tree @var array $category @var array $fields @var array $types */
$cid = (int) $campaign['id'];
$typeHelp = [
    'text'     => 'Single line',
    'textarea' => 'Long text',
    'select'   => 'Dropdown (fixed options)',
    'suggest'  => 'Dropdown that remembers previous values',
    'image'    => 'Image upload',
    'date'     => 'Date picker',
    'multi'    => 'Multiple values (comma-separated)',
];
?>
<div class="layout">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main">
        <div class="page-toolbar">
            <a class="btn btn-sm" href="/campaign/<?= $cid ?>">← Back</a>
        </div>
        <h1 style="color:var(--gold);">Fields — <?= e($category['name']) ?></h1>
        <p class="muted">These fields appear on every page in this category (and its sub-categories, unless they define their own).</p>

        <?php if (empty($fields)): ?>
            <form method="post" action="/campaign/<?= $cid ?>/category/<?= (int) $category['id'] ?>/fields/defaults" style="margin-bottom:16px;">
                <?= Csrf::field() ?>
                <button class="btn" type="submit">✨ Load default fields for “<?= e($category['name']) ?>”</button>
            </form>
        <?php endif; ?>

        <form method="post" action="/campaign/<?= $cid ?>/category/<?= (int) $category['id'] ?>/fields" id="fields-editor">
            <?= Csrf::field() ?>

            <div id="field-rows">
                <?php foreach ($fields as $f): ?>
                    <div class="field-edit-row">
                        <input type="text" name="label[]" placeholder="Field label" value="<?= e($f['label']) ?>">
                        <select name="type[]">
                            <?php foreach ($types as $t): ?>
                                <option value="<?= e($t) ?>" <?= $f['type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="options[]" placeholder="Options (comma-separated, for dropdowns)"
                               value="<?= e(implode(', ', $f['options'])) ?>">
                        <button type="button" class="btn btn-sm field-remove">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="btn btn-sm" id="add-field">+ Add field</button>
            <div style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">Save fields</button>
            </div>
        </form>

        <div class="panel" style="margin-top:22px;">
            <strong>Field types</strong>
            <ul class="muted" style="margin:8px 0 0;">
                <?php foreach ($typeHelp as $t => $desc): ?>
                    <li><code><?= e($t) ?></code> — <?= e($desc) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <template id="field-row-template">
            <div class="field-edit-row">
                <input type="text" name="label[]" placeholder="Field label">
                <select name="type[]">
                    <?php foreach ($types as $t): ?>
                        <option value="<?= e($t) ?>"><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="options[]" placeholder="Options (comma-separated, for dropdowns)">
                <button type="button" class="btn btn-sm field-remove">✕</button>
            </div>
        </template>
    </main>
</div>
