<?php

use App\Lib\Csrf;

/**
 * Campaign sidebar: custom category tree + pages.
 * Expects: $campaign, $tree (['roots'=>..,'uncategorised'=>..]), optional $activeSlug.
 * @var array $campaign
 * @var array $tree
 */
$activeSlug = $activeSlug ?? null;
$cid = (int) $campaign['id'];

if (!function_exists('codex_render_category')) {
    function codex_render_category(array $cat, int $cid, ?string $activeSlug): void
    {
        $icon = $cat['icon'] ? $cat['icon'] . ' ' : '';
        echo '<details class="tree-cat" open>';
        echo '<summary>' . $icon . e($cat['name']) . '</summary>';
        echo '<div class="tree-sub">';

        foreach ($cat['pages'] as $p) {
            $active = ($activeSlug === $p['slug']) ? ' active' : '';
            echo '<a class="tree-page' . $active . '" href="/campaign/' . $cid . '/page/' . rawurlencode($p['slug']) . '">' . e($p['title']) . '</a>';
        }
        foreach ($cat['children'] as $child) {
            codex_render_category($child, $cid, $activeSlug);
        }

        echo '</div></details>';
    }
}
?>
<aside class="sidebar">
    <div class="campaign-name"><?= e($campaign['name']) ?></div>

    <?php foreach ($tree['roots'] as $cat): ?>
        <?php codex_render_category($cat, $cid, $activeSlug); ?>
    <?php endforeach; ?>

    <?php if (!empty($tree['uncategorised'])): ?>
        <details class="tree-cat" open>
            <summary>Uncategorised</summary>
            <div class="tree-sub">
                <?php foreach ($tree['uncategorised'] as $p): ?>
                    <a class="tree-page <?= $activeSlug === $p['slug'] ? 'active' : '' ?>"
                       href="/campaign/<?= $cid ?>/page/<?= rawurlencode($p['slug']) ?>"><?= e($p['title']) ?></a>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>

    <div class="sidebar-add">
        <a class="btn btn-sm btn-primary" href="/campaign/<?= $cid ?>/new">+ New page</a>
        <details style="margin-top:10px;">
            <summary class="muted" style="cursor:pointer;">+ Category</summary>
            <form method="post" action="/campaign/<?= $cid ?>/categories" style="margin-top:8px;">
                <?= Csrf::field() ?>
                <input type="text" name="name" placeholder="Category name" required>
                <input type="text" name="icon" placeholder="Icon (emoji, optional)" style="margin-top:6px;">
                <button class="btn btn-sm" type="submit" style="margin-top:6px;">Add</button>
            </form>
        </details>
    </div>
</aside>
