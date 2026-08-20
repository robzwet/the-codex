<?php

declare(strict_types=1);

namespace App\Lib;

final class Slug
{
    /** Make a URL-safe slug from a title. */
    public static function make(string $text): string
    {
        $text = strtolower(trim($text));
        // Transliterate accented characters where possible.
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        if ($translit !== false) {
            $text = $translit;
        }
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        return $text !== '' ? $text : 'page';
    }

    /**
     * Ensure the slug is unique within a campaign, appending -2, -3, ...
     * $ignoreId lets an existing page keep its slug when re-saved.
     */
    public static function unique(string $base, int $campaignId, ?int $ignoreId = null): string
    {
        $slug = $base;
        $n = 1;
        while (true) {
            $sql = 'SELECT id FROM pages WHERE campaign_id = ? AND slug = ?';
            $params = [$campaignId, $slug];
            if ($ignoreId !== null) {
                $sql .= ' AND id <> ?';
                $params[] = $ignoreId;
            }
            $clash = Db::run($sql, $params)->fetch();
            if (!$clash) {
                return $slug;
            }
            $n++;
            $slug = $base . '-' . $n;
        }
    }
}
