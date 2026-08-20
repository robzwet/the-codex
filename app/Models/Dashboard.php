<?php

declare(strict_types=1);

namespace App\Models;

use App\Lib\Db;

/**
 * Aggregates a campaign's structured data into the dashboard/hub sections,
 * mirroring the "Campagne Overzicht" the user maintained by hand in Obsidian.
 */
final class Dashboard
{
    public static function build(int $campaignId): array
    {
        return [
            'party'    => self::pagesWithMeta($campaignId, self::catIds($campaignId, 'party'), ['player', 'class', 'race', 'level', 'status']),
            'quests'   => self::quests($campaignId),
            'sessions' => self::sessions($campaignId),
            'enemies'  => self::livingEnemies($campaignId),
            'items'    => self::pagesWithMeta($campaignId, self::catIds($campaignId, 'items'), ['rarity', 'owner', 'status']),
        ];
    }

    /** Quests grouped into Active / Open thread / Completed / (Other). */
    private static function quests(int $campaignId): array
    {
        $rows = self::pagesWithMeta($campaignId, self::catIds($campaignId, 'quests'), ['quest-giver', 'reward', 'status', 'started-session', 'completed-session']);
        $buckets = ['Active' => [], 'Open thread' => [], 'Completed' => [], 'Other' => []];
        foreach ($rows as $r) {
            $status = $r['meta']['status'] ?: 'Other';
            $buckets[$status][] = $r;
        }
        return array_filter($buckets, fn($b) => !empty($b));
    }

    /** Sessions ordered by their "Session number" field. */
    private static function sessions(int $campaignId): array
    {
        $rows = self::pagesWithMeta($campaignId, self::catIds($campaignId, 'sessions'), ['session-number', 'played-on', 'note-taker']);
        usort($rows, function ($a, $b) {
            $an = $a['meta']['session-number'];
            $bn = $b['meta']['session-number'];
            if ($an === '' && $bn === '') return strcmp($a['title'], $b['title']);
            if ($an === '') return 1;
            if ($bn === '') return -1;
            return (int) $an <=> (int) $bn;
        });
        return $rows;
    }

    /** Enemies whose status is Alive or Unknown. */
    private static function livingEnemies(int $campaignId): array
    {
        $rows = self::pagesWithMeta($campaignId, self::catIds($campaignId, 'enemies'), ['status', 'race']);
        return array_values(array_filter($rows, function ($r) {
            $s = mb_strtolower($r['meta']['status']);
            return $s === '' || $s === 'alive' || $s === 'unknown';
        }));
    }

    /** A category (matched by name) plus all its descendant category ids. */
    private static function catIds(int $campaignId, string $name): array
    {
        $cats = Db::run('SELECT id, name, parent_id FROM categories WHERE campaign_id = ?', [$campaignId])->fetchAll();
        $root = null;
        foreach ($cats as $c) {
            if (mb_strtolower(trim($c['name'])) === mb_strtolower($name)) {
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

    /**
     * Pages in the given categories with a fixed set of meta keys attached.
     * Keys 'player' / 'note-taker' are resolved from user id to username.
     * @return array<int,array{title:string,slug:string,meta:array<string,string>}>
     */
    private static function pagesWithMeta(int $campaignId, array $catIds, array $keys): array
    {
        if (!$catIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($catIds), '?'));
        $pages = Db::run(
            "SELECT id, title, slug FROM pages WHERE campaign_id = ? AND category_id IN ($in) ORDER BY title",
            array_merge([$campaignId], $catIds)
        )->fetchAll();
        if (!$pages) {
            return [];
        }

        $ids = array_column($pages, 'id');
        $pin = implode(',', array_fill(0, count($ids), '?'));
        $meta = Db::run("SELECT page_id, meta_key, meta_value FROM page_meta WHERE page_id IN ($pin)", $ids)->fetchAll();
        $byPage = [];
        foreach ($meta as $m) {
            $byPage[(int) $m['page_id']][$m['meta_key']] = $m['meta_value'];
        }

        $out = [];
        foreach ($pages as $p) {
            $mm = $byPage[(int) $p['id']] ?? [];
            foreach (['player', 'note-taker'] as $userKey) {
                if (isset($mm[$userKey]) && ctype_digit((string) $mm[$userKey])) {
                    $mm[$userKey] = User::name((int) $mm[$userKey]) ?? $mm[$userKey];
                }
            }
            $filtered = [];
            foreach ($keys as $k) {
                $filtered[$k] = $mm[$k] ?? '';
            }
            $out[] = ['title' => $p['title'], 'slug' => $p['slug'], 'meta' => $filtered];
        }
        return $out;
    }
}
