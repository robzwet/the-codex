<?php

declare(strict_types=1);

namespace App\Models;

use App\Lib\Db;

/** Free-form page tags, optionally hierarchical ("quest/open-thread"). */
final class Tag
{
    /** @return string[] tags on a page */
    public static function forPage(int $pageId): array
    {
        return array_column(
            Db::run('SELECT tag FROM page_tags WHERE page_id = ? ORDER BY tag', [$pageId])->fetchAll(),
            'tag'
        );
    }

    /** Replace a page's tags from a raw comma-separated string. */
    public static function setForPage(int $campaignId, int $pageId, string $raw): void
    {
        Db::run('DELETE FROM page_tags WHERE page_id = ?', [$pageId]);
        foreach (self::parse($raw) as $tag) {
            Db::run(
                'INSERT INTO page_tags (campaign_id, page_id, tag) VALUES (?, ?, ?)',
                [$campaignId, $pageId, $tag]
            );
        }
    }

    /** All distinct tags in a campaign with usage counts. */
    public static function allForCampaign(int $campaignId): array
    {
        return Db::run(
            'SELECT tag, COUNT(*) AS n FROM page_tags WHERE campaign_id = ? GROUP BY tag ORDER BY tag',
            [$campaignId]
        )->fetchAll();
    }

    /** Pages carrying a given tag. */
    public static function pagesWithTag(int $campaignId, string $tag): array
    {
        return Db::run(
            'SELECT p.title, p.slug FROM page_tags t
             JOIN pages p ON p.id = t.page_id
             WHERE t.campaign_id = ? AND t.tag = ?
             ORDER BY p.title',
            [$campaignId, $tag]
        )->fetchAll();
    }

    /** Normalise a raw tag string into a clean, de-duplicated list. */
    public static function parse(string $raw): array
    {
        $parts = preg_split('/[,\r\n]+/', $raw);
        $seen = [];
        foreach ($parts as $p) {
            $p = trim(mb_strtolower($p));
            $p = preg_replace('#\s*/\s*#', '/', $p);      // tidy "a / b" -> "a/b"
            $p = preg_replace('/[^a-z0-9\/_-]+/', '-', $p); // safe characters only
            $p = trim($p, '-/');
            if ($p !== '') {
                $seen[$p] = true;
            }
        }
        return array_keys($seen);
    }
}
