<?php

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Page;
use App\Models\Template;

/**
 * Renders the typed template fields for a category.
 * Expects: $fields (array), $values (assoc field_key=>value), $campaignId (int).
 * @var array $fields
 * @var array $values
 * @var int $campaignId
 */
$values = $values ?? [];

if (empty($fields)) {
    echo '<p class="muted">This category has no template fields. Add some with “Manage fields”, or just write below.</p>';
    return;
}

foreach ($fields as $f):
    $key = $f['field_key'];
    $val = $values[$key] ?? '';
    $id = 'f_' . preg_replace('/[^a-z0-9_-]/i', '', $key);
    ?>
    <div class="field-row">
        <label for="<?= e($id) ?>"><?= e($f['label']) ?></label>

        <?php if ($f['type'] === 'textarea'): ?>
            <textarea id="<?= e($id) ?>" name="field[<?= e($key) ?>]" rows="3"><?= e($val) ?></textarea>

        <?php elseif ($f['type'] === 'date'): ?>
            <input type="date" id="<?= e($id) ?>" name="field[<?= e($key) ?>]" value="<?= e($val) ?>">

        <?php elseif ($f['type'] === 'select'): ?>
            <select id="<?= e($id) ?>" name="field[<?= e($key) ?>]">
                <option value="">— none —</option>
                <?php foreach ($f['options'] as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= $val === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>

        <?php elseif ($f['type'] === 'link'): ?>
            <?php
            // A link field may target one or more categories; aggregate their pages.
            $targetCats = $f['options'] ?: [];
            $catIds = [];
            foreach ($targetCats as $tc) {
                $catIds = array_merge($catIds, Category::idsByName($campaignId, $tc));
            }
            $titles = $catIds ? Page::titlesInCategories($campaignId, array_values(array_unique($catIds))) : [];
            ?>
            <input type="text" id="<?= e($id) ?>" name="field[<?= e($key) ?>]" value="<?= e($val) ?>"
                   list="dl_<?= e($id) ?>"
                   placeholder="<?= $targetCats ? 'Pick an existing ' . e(implode(' / ', $targetCats)) . ' — or type a new name' : 'Pick an existing page' ?>">
            <datalist id="dl_<?= e($id) ?>">
                <?php foreach ($titles as $t): ?><option value="<?= e($t) ?>"></option><?php endforeach; ?>
            </datalist>

        <?php elseif ($f['type'] === 'user'): ?>
            <select id="<?= e($id) ?>" name="field[<?= e($key) ?>]">
                <option value="">— nobody —</option>
                <?php foreach (Campaign::members($campaignId) as $mem): ?>
                    <option value="<?= (int) $mem['id'] ?>" <?= $val === (string) $mem['id'] ? 'selected' : '' ?>>
                        <?= e($mem['username']) ?><?= $mem['role'] === 'gm' ? ' (GM)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>

        <?php elseif ($f['type'] === 'suggest' || $f['type'] === 'multi'): ?>
            <?php
            $suggestions = array_values(array_unique(array_merge(
                $f['options'],
                Template::suggestions($campaignId, $key)
            )));
            $isMulti = $f['type'] === 'multi';
            ?>
            <input type="text" id="<?= e($id) ?>" name="field[<?= e($key) ?>]" value="<?= e($val) ?>"
                   list="dl_<?= e($id) ?>"
                   placeholder="<?= $isMulti ? 'Comma-separated — pick or type new' : 'Pick existing or type new' ?>">
            <datalist id="dl_<?= e($id) ?>">
                <?php foreach ($suggestions as $s): ?>
                    <option value="<?= e($s) ?>"></option>
                <?php endforeach; ?>
            </datalist>

        <?php elseif ($f['type'] === 'image'): ?>
            <?php if ($val !== ''): ?>
                <div class="field-image-preview"><img src="<?= e($val) ?>" alt=""></div>
                <label class="inline"><input type="checkbox" name="clear_image[<?= e($key) ?>]" value="1"> Remove current image</label>
            <?php endif; ?>
            <input type="file" id="<?= e($id) ?>" name="image_file[<?= e($key) ?>]" accept="image/*">
            <input type="hidden" name="field[<?= e($key) ?>]" value="<?= e($val) ?>">

        <?php else: /* text */ ?>
            <input type="text" id="<?= e($id) ?>" name="field[<?= e($key) ?>]" value="<?= e($val) ?>">
        <?php endif; ?>
    </div>
<?php endforeach; ?>
