<?php

declare(strict_types=1);

namespace App\Models;

use App\Lib\Db;

final class Category
{
    /**
     * Default category tree seeded into every new campaign. Mirrors the
     * Obsidian structure from the reference notes; users can freely
     * add/rename/nest/delete afterwards.
     *
     * @var array<int,array{name:string,icon:string,children?:string[]}>
     */
    private const DEFAULTS = [
        ['name' => 'Party',              'icon' => '🛡️'],
        ['name' => 'Sessions',           'icon' => '📜'],
        ['name' => 'NPCs',               'icon' => '🧝', 'children' => ['Enemies', 'Friendly']],
        ['name' => 'Organizations',      'icon' => '⚜️'],
        ['name' => 'Places',             'icon' => '🏰'],
        ['name' => 'Points of Interest', 'icon' => '📍'],
        ['name' => 'Quests',             'icon' => '📌'],
        ['name' => 'Items',              'icon' => '⚔️'],
    ];

    public static function seedDefaults(int $campaignId): void
    {
        $order = 0;
        foreach (self::DEFAULTS as $cat) {
            $parentId = self::create($campaignId, $cat['name'], null, $cat['icon'], $order++);
            foreach ($cat['children'] ?? [] as $childOrder => $childName) {
                self::create($campaignId, $childName, $parentId, null, $childOrder);
            }
        }
    }

    /** Add any missing default categories (by name) to an existing campaign. Returns count added. */
    public static function ensureDefaults(int $campaignId): int
    {
        $existing = [];
        foreach (self::forCampaign($campaignId) as $c) {
            $existing[mb_strtolower(trim($c['name']))] = (int) $c['id'];
        }
        $added = 0;
        $order = 1000;
        foreach (self::DEFAULTS as $cat) {
            $key = mb_strtolower($cat['name']);
            if (!isset($existing[$key])) {
                $existing[$key] = self::create($campaignId, $cat['name'], null, $cat['icon'], $order++);
                $added++;
            }
            foreach ($cat['children'] ?? [] as $childName) {
                if (!isset($existing[mb_strtolower($childName)])) {
                    self::create($campaignId, $childName, $existing[$key], null, 0);
                    $existing[mb_strtolower($childName)] = 1;
                    $added++;
                }
            }
        }
        return $added;
    }

    public static function create(int $campaignId, string $name, ?int $parentId, ?string $icon, int $sortOrder = 0): int
    {
        Db::run(
            'INSERT INTO categories (campaign_id, name, parent_id, icon, sort_order) VALUES (?, ?, ?, ?, ?)',
            [$campaignId, trim($name), $parentId, $icon, $sortOrder]
        );
        return (int) Db::conn()->lastInsertId();
    }

    public static function rename(int $id, int $campaignId, string $name): void
    {
        Db::run(
            'UPDATE categories SET name = ? WHERE id = ? AND campaign_id = ?',
            [trim($name), $id, $campaignId]
        );
    }

    public static function delete(int $id, int $campaignId): void
    {
        // Child categories cascade; pages fall back to uncategorised (ON DELETE SET NULL).
        Db::run('DELETE FROM categories WHERE id = ? AND campaign_id = ?', [$id, $campaignId]);
    }

    /** A category (matched by name) plus all its descendant category ids. */
    public static function idsByName(int $campaignId, string $name): array
    {
        $cats = Db::run('SELECT id, name, parent_id FROM categories WHERE campaign_id = ?', [$campaignId])->fetchAll();
        $root = null;
        foreach ($cats as $c) {
            if (mb_strtolower(trim($c['name'])) === mb_strtolower(trim($name))) {
                $root = (int) $c['id'];
                break;
            }
        }
        if ($root === null) {
            return [];
        }
        $childrenOf = [];
        foreach ($cats as $c) {
            $childrenOf[(int) ($c['parent_id'] ?? 0)][] = (int) $c['id'];
        }
        $ids = [$root];
        $stack = [$root];
        while ($stack) {
            $x = array_pop($stack);
            foreach ($childrenOf[$x] ?? [] as $child) {
                $ids[] = $child;
                $stack[] = $child;
            }
        }
        return array_values(array_unique($ids));
    }

    /** Flat list for a campaign, ordered for tree building. */
    public static function forCampaign(int $campaignId): array
    {
        return Db::run(
            'SELECT * FROM categories WHERE campaign_id = ? ORDER BY parent_id IS NOT NULL, sort_order, name',
            [$campaignId]
        )->fetchAll();
    }

    /**
     * Build the sidebar tree: top-level categories, each with children and their
     * pages attached. Returns a nested structure for the view to render.
     */
    public static function tree(int $campaignId): array
    {
        $cats = self::forCampaign($campaignId);
        $pages = Page::listForCampaign($campaignId);

        $pagesByCat = [];
        $uncategorised = [];
        foreach ($pages as $p) {
            if ($p['category_id'] === null) {
                $uncategorised[] = $p;
            } else {
                $pagesByCat[(int) $p['category_id']][] = $p;
            }
        }

        $byId = [];
        foreach ($cats as $c) {
            $c['children'] = [];
            $c['pages'] = $pagesByCat[(int) $c['id']] ?? [];
            $byId[(int) $c['id']] = $c;
        }

        $roots = [];
        foreach ($byId as $id => $c) {
            if ($c['parent_id'] !== null && isset($byId[(int) $c['parent_id']])) {
                $byId[(int) $c['parent_id']]['children'][] = &$byId[$id];
            } else {
                $roots[] = &$byId[$id];
            }
        }

        return ['roots' => $roots, 'uncategorised' => $uncategorised];
    }
}
