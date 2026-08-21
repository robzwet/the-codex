<?php

declare(strict_types=1);

namespace App\Lib;

use App\Models\Page;
use App\Models\Template;

/**
 * Obsidian-style [[wikilinks]].
 *
 * Notes store links as literal [[Title]] or [[Title|alias]] tokens in the body.
 *  - extract()  finds every token (used to keep the `links` table in sync on save)
 *  - render()   turns tokens into <a> tags, resolving to real pages where they
 *               exist and rendering "red links" (create-this-page) where they don't
 *  - sync()     rewrites the links table for a page after a save
 */
final class WikiLinks
{
    private const PATTERN = '/\[\[\s*([^\]|]+?)\s*(?:\|\s*([^\]]+?)\s*)?\]\]/u';

    /** @return string[] Unique link target titles found in the HTML. */
    public static function extract(string $html): array
    {
        preg_match_all(self::PATTERN, $html, $m);
        $titles = array_map('trim', $m[1]);
        // De-duplicate case-insensitively while preserving first-seen casing.
        $seen = [];
        foreach ($titles as $t) {
            $key = mb_strtolower($t);
            if ($t !== '' && !isset($seen[$key])) {
                $seen[$key] = $t;
            }
        }
        return array_values($seen);
    }

    /** Replace [[tokens]] with resolved anchors for display. */
    public static function render(string $html, int $campaignId): string
    {
        return preg_replace_callback(self::PATTERN, function (array $m) use ($campaignId): string {
            $title = trim($m[1]);
            $label = isset($m[2]) && $m[2] !== '' ? trim($m[2]) : $title;

            $page = Db::run(
                'SELECT slug FROM pages WHERE campaign_id = ? AND (title = ? OR slug = ?) LIMIT 1',
                [$campaignId, $title, Slug::make($title)]
            )->fetch();

            $labelEsc = htmlspecialchars($label, ENT_QUOTES);

            if ($page) {
                $href = '/campaign/' . $campaignId . '/page/' . rawurlencode($page['slug']);
                return '<a class="wikilink" href="' . $href . '">' . $labelEsc . '</a>';
            }

            // Red link — offers to create the page with this title.
            $href = '/campaign/' . $campaignId . '/new?title=' . rawurlencode($title);
            return '<a class="wikilink wikilink--new" href="' . $href . '" title="Create this page">' . $labelEsc . '</a>';
        }, $html) ?? $html;
    }

    /** Rebuild the links table for a source page from its body HTML. */
    public static function sync(int $campaignId, int $sourcePageId, string $html): void
    {
        Db::run('DELETE FROM links WHERE source_page_id = ?', [$sourcePageId]);

        foreach (self::extract($html) as $title) {
            $target = Db::run(
                'SELECT id FROM pages WHERE campaign_id = ? AND (title = ? OR slug = ?) LIMIT 1',
                [$campaignId, $title, Slug::make($title)]
            )->fetch();

            Db::run(
                'INSERT INTO links (campaign_id, source_page_id, target_page_id, target_title) VALUES (?, ?, ?, ?)',
                [$campaignId, $sourcePageId, $target ? (int) $target['id'] : null, $title]
            );
        }
    }

    /**
     * Add relationship links from a page's `link`-type template fields (e.g. an
     * NPC's Faction, a Quest's giver) so those references appear as backlinks on
     * the target page. Run AFTER sync() so it isn't wiped.
     */
    public static function syncFieldLinks(int $campaignId, int $pageId, ?int $categoryId): void
    {
        $meta = [];
        foreach (Page::meta($pageId) as $m) {
            $meta[$m['meta_key']] = $m['meta_value'];
        }
        foreach (Template::fieldsFor($campaignId, $categoryId) as $f) {
            if ($f['type'] !== 'link') {
                continue;
            }
            $title = trim($meta[$f['field_key']] ?? '');
            if ($title === '') {
                continue;
            }
            $target = Db::run(
                'SELECT id FROM pages WHERE campaign_id = ? AND (title = ? OR slug = ?) LIMIT 1',
                [$campaignId, $title, Slug::make($title)]
            )->fetch();
            Db::run(
                'INSERT INTO links (campaign_id, source_page_id, target_page_id, target_title) VALUES (?, ?, ?, ?)',
                [$campaignId, $pageId, $target ? (int) $target['id'] : null, $title]
            );
        }
    }

    /**
     * When a new page is created, resolve any previously-unresolved links that
     * pointed at its title, so existing red links light up automatically.
     */
    public static function resolveInbound(int $campaignId, int $pageId, string $title): void
    {
        Db::run(
            'UPDATE links SET target_page_id = ?
             WHERE campaign_id = ? AND target_page_id IS NULL
               AND LOWER(target_title) = LOWER(?)',
            [$pageId, $campaignId, $title]
        );
    }
}
