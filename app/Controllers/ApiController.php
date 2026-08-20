<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Guard;
use App\Lib\Slug;
use App\Lib\View;
use App\Models\Page;
use App\Models\Template;

final class ApiController
{
    /**
     * Autocomplete for the [[ ]] link picker. Returns matching pages plus a
     * synthetic "create" suggestion so new pages can be linked before they exist.
     */
    public static function search(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        $q = trim($_GET['q'] ?? '');

        $results = [];
        if ($q !== '') {
            foreach (Page::search((int) $campaign['id'], $q) as $p) {
                $results[] = ['title' => $p['title'], 'slug' => $p['slug'], 'exists' => true];
            }
            $exact = array_filter($results, fn($r) => mb_strtolower($r['title']) === mb_strtolower($q));
            if (!$exact) {
                $results[] = ['title' => $q, 'slug' => Slug::make($q), 'exists' => false];
            }
        }

        json_response(['results' => $results]);
    }

    /**
     * Renders the template-field inputs for a category (HTML partial). Used by
     * the page form to swap fields when the category selection changes.
     */
    public static function fields(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        $cid = (int) $campaign['id'];
        $categoryId = ($_GET['category'] ?? '') !== '' ? (int) $_GET['category'] : null;

        // Prefill from an existing page's meta (only if it belongs to this campaign).
        $values = [];
        if (($_GET['page'] ?? '') !== '') {
            $page = Page::findById((int) $_GET['page']);
            if ($page && (int) $page['campaign_id'] === $cid) {
                foreach (Page::meta((int) $page['id']) as $m) {
                    $values[$m['meta_key']] = $m['meta_value'];
                }
            }
        }

        header('Content-Type: text/html; charset=utf-8');
        echo View::capture('partials/fields', [
            'fields'     => Template::fieldsFor($cid, $categoryId),
            'values'     => $values,
            'campaignId' => $cid,
        ]);
    }
}
