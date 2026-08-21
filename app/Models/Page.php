<?php

declare(strict_types=1);

namespace App\Models;

use App\Lib\Db;
use App\Lib\Sanitizer;
use App\Lib\Slug;
use App\Lib\WikiLinks;

final class Page
{
    public static function find(int $campaignId, string $slug): ?array
    {
        $row = Db::run(
            'SELECT * FROM pages WHERE campaign_id = ? AND slug = ?',
            [$campaignId, $slug]
        )->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $row = Db::run('SELECT * FROM pages WHERE id = ?', [$id])->fetch();
        return $row ?: null;
    }

    /** Resolve a page in a campaign by its title (or slug). */
    public static function findByTitle(int $campaignId, string $title): ?array
    {
        $row = Db::run(
            'SELECT * FROM pages WHERE campaign_id = ? AND (title = ? OR slug = ?) LIMIT 1',
            [$campaignId, $title, Slug::make($title)]
        )->fetch();
        return $row ?: null;
    }

    /** Titles of pages in the given categories — for link-field pickers. */
    public static function titlesInCategories(int $campaignId, array $catIds): array
    {
        if (!$catIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($catIds), '?'));
        return array_column(
            Db::run(
                "SELECT title FROM pages WHERE campaign_id = ? AND category_id IN ($in) ORDER BY title",
                array_merge([$campaignId], $catIds)
            )->fetchAll(),
            'title'
        );
    }

    /** Lightweight list (id/title/slug/category/kind) used for the tree + search. */
    public static function listForCampaign(int $campaignId): array
    {
        return Db::run(
            'SELECT id, title, slug, category_id, kind FROM pages WHERE campaign_id = ? ORDER BY title',
            [$campaignId]
        )->fetchAll();
    }

    /**
     * @param array<int,array{key:string,value:string}> $meta
     */
    public static function create(
        int $campaignId,
        string $title,
        ?int $categoryId,
        string $kind,
        string $rawHtml,
        int $userId,
        array $meta = []
    ): int {
        $title = trim($title) !== '' ? trim($title) : 'Untitled';
        $slug = Slug::unique(Slug::make($title), $campaignId);
        $html = Sanitizer::clean($rawHtml);

        Db::run(
            'INSERT INTO pages (campaign_id, category_id, title, slug, kind, body_html, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$campaignId, $categoryId, $title, $slug, $kind, $html, $userId, $userId]
        );
        $id = (int) Db::conn()->lastInsertId();

        self::saveRevision($id, $title, $html, $userId);
        self::saveMeta($id, $meta);
        WikiLinks::sync($campaignId, $id, $html);
        WikiLinks::syncFieldLinks($campaignId, $id, $categoryId);
        // Light up any red links that were waiting for a page with this title.
        WikiLinks::resolveInbound($campaignId, $id, $title);

        return $id;
    }

    /**
     * @param array<int,array{key:string,value:string}> $meta
     */
    public static function update(
        int $id,
        int $campaignId,
        string $title,
        ?int $categoryId,
        string $rawHtml,
        int $userId,
        array $meta = []
    ): void {
        $title = trim($title) !== '' ? trim($title) : 'Untitled';
        $slug = Slug::unique(Slug::make($title), $campaignId, $id);
        $html = Sanitizer::clean($rawHtml);

        Db::run(
            'UPDATE pages SET title = ?, slug = ?, category_id = ?, body_html = ?, updated_by = ?
             WHERE id = ? AND campaign_id = ?',
            [$title, $slug, $categoryId, $html, $userId, $id, $campaignId]
        );

        self::saveRevision($id, $title, $html, $userId);
        self::saveMeta($id, $meta);
        WikiLinks::sync($campaignId, $id, $html);
        WikiLinks::syncFieldLinks($campaignId, $id, $categoryId);
        WikiLinks::resolveInbound($campaignId, $id, $title);
    }

    public static function delete(int $id, int $campaignId): void
    {
        Db::run('DELETE FROM pages WHERE id = ? AND campaign_id = ?', [$id, $campaignId]);
    }

    // --- Infobox meta ---------------------------------------------------------

    public static function meta(int $pageId): array
    {
        return Db::run(
            'SELECT meta_key, meta_value FROM page_meta WHERE page_id = ? ORDER BY sort_order, id',
            [$pageId]
        )->fetchAll();
    }

    /** @param array<int,array{key:string,value:string}> $meta */
    public static function saveMeta(int $pageId, array $meta): void
    {
        Db::run('DELETE FROM page_meta WHERE page_id = ?', [$pageId]);
        $order = 0;
        foreach ($meta as $row) {
            $key = trim($row['key'] ?? '');
            if ($key === '') {
                continue;
            }
            Db::run(
                'INSERT INTO page_meta (page_id, meta_key, meta_value, sort_order) VALUES (?, ?, ?, ?)',
                [$pageId, $key, trim($row['value'] ?? ''), $order++]
            );
        }
    }

    // --- Backlinks & history --------------------------------------------------

    /** Pages that link TO this one. */
    public static function backlinks(int $pageId): array
    {
        return Db::run(
            'SELECT p.title, p.slug
             FROM links l
             JOIN pages p ON p.id = l.source_page_id
             WHERE l.target_page_id = ?
             ORDER BY p.title',
            [$pageId]
        )->fetchAll();
    }

    /** Usernames of the original author and last editor. */
    public static function authors(int $pageId): array
    {
        $row = Db::run(
            'SELECT cu.username AS created_by, uu.username AS updated_by, p.created_at, p.updated_at
             FROM pages p
             LEFT JOIN users cu ON cu.id = p.created_by
             LEFT JOIN users uu ON uu.id = p.updated_by
             WHERE p.id = ?',
            [$pageId]
        )->fetch();
        return $row ?: ['created_by' => null, 'updated_by' => null, 'created_at' => null, 'updated_at' => null];
    }

    public static function revisions(int $pageId): array
    {
        return Db::run(
            'SELECT r.id, r.title, r.edited_at, u.username
             FROM page_revisions r
             LEFT JOIN users u ON u.id = r.edited_by
             WHERE r.page_id = ?
             ORDER BY r.edited_at DESC, r.id DESC',
            [$pageId]
        )->fetchAll();
    }

    public static function revision(int $revisionId, int $pageId): ?array
    {
        $row = Db::run(
            'SELECT * FROM page_revisions WHERE id = ? AND page_id = ?',
            [$revisionId, $pageId]
        )->fetch();
        return $row ?: null;
    }

    /**
     * Previous/next session pages, ordered by their "Session number" field.
     * Returns ['prev' => ?row, 'next' => ?row].
     */
    public static function sessionNeighbors(int $campaignId, int $pageId): array
    {
        $sessionCatIds = [];
        foreach (Db::run('SELECT id FROM categories WHERE campaign_id = ?', [$campaignId])->fetchAll() as $c) {
            if (Template::isSessionCategory($campaignId, (int) $c['id'])) {
                $sessionCatIds[] = (int) $c['id'];
            }
        }
        if (!$sessionCatIds) {
            return ['prev' => null, 'next' => null];
        }

        $in = implode(',', array_fill(0, count($sessionCatIds), '?'));
        $rows = Db::run(
            "SELECT p.id, p.title, p.slug,
                    CAST(NULLIF(pm.meta_value, '') AS UNSIGNED) AS num
             FROM pages p
             LEFT JOIN page_meta pm ON pm.page_id = p.id AND pm.meta_key = 'session-number'
             WHERE p.campaign_id = ? AND p.category_id IN ($in)
             ORDER BY num IS NULL, num, p.title",
            array_merge([$campaignId], $sessionCatIds)
        )->fetchAll();

        $idx = null;
        foreach ($rows as $i => $r) {
            if ((int) $r['id'] === $pageId) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            return ['prev' => null, 'next' => null];
        }
        return [
            'prev' => $rows[$idx - 1] ?? null,
            'next' => $rows[$idx + 1] ?? null,
        ];
    }

    public static function search(int $campaignId, string $query): array
    {
        $like = '%' . $query . '%';
        return Db::run(
            'SELECT id, title, slug FROM pages
             WHERE campaign_id = ? AND title LIKE ?
             ORDER BY title LIMIT 10',
            [$campaignId, $like]
        )->fetchAll();
    }

    private static function saveRevision(int $pageId, string $title, string $html, int $userId): void
    {
        Db::run(
            'INSERT INTO page_revisions (page_id, title, body_html, edited_by) VALUES (?, ?, ?, ?)',
            [$pageId, $title, $html, $userId]
        );
    }
}
