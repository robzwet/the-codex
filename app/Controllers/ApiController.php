<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Guard;
use App\Lib\Slug;
use App\Models\Page;

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
                $results[] = [
                    'title'  => $p['title'],
                    'slug'   => $p['slug'],
                    'exists' => true,
                ];
            }
            // Offer to create the typed title if there's no exact match.
            $exact = array_filter($results, fn($r) => mb_strtolower($r['title']) === mb_strtolower($q));
            if (!$exact) {
                $results[] = ['title' => $q, 'slug' => Slug::make($q), 'exists' => false];
            }
        }

        json_response(['results' => $results]);
    }
}
