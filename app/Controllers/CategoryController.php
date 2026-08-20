<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Csrf;
use App\Lib\Flash;
use App\Lib\Guard;
use App\Models\Category;

final class CategoryController
{
    public static function store(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        Csrf::check();

        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $parentId = ($_POST['parent_id'] ?? '') !== '' ? (int) $_POST['parent_id'] : null;
            Category::create((int) $campaign['id'], $name, $parentId, $_POST['icon'] ?? null);
            Flash::set('success', 'Category added.');
        }
        redirect('/campaign/' . $campaign['id']);
    }

    public static function rename(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        Csrf::check();
        Category::rename((int) $params['cid'], (int) $campaign['id'], $_POST['name'] ?? '');
        redirect('/campaign/' . $campaign['id']);
    }

    public static function delete(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        Csrf::check();
        Category::delete((int) $params['cid'], (int) $campaign['id']);
        Flash::set('success', 'Category deleted.');
        redirect('/campaign/' . $campaign['id']);
    }
}
