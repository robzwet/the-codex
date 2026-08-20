<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Guard;
use App\Lib\View;
use App\Models\Category;
use App\Models\Tag;

final class TagController
{
    /** Tag index: a cloud of all tags, and the pages for a selected tag. */
    public static function index(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        $cid = (int) $campaign['id'];
        $tag = trim($_GET['tag'] ?? '');

        View::render('tags/index', [
            'campaign' => $campaign,
            'tree'     => Category::tree($cid),
            'allTags'  => Tag::allForCampaign($cid),
            'tag'      => $tag,
            'pages'    => $tag !== '' ? Tag::pagesWithTag($cid, $tag) : [],
        ], 'app_layout');
    }
}
