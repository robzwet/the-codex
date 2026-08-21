<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Csrf;
use App\Lib\Db;
use App\Lib\Flash;
use App\Lib\Guard;
use App\Lib\View;
use App\Models\Category;
use App\Models\Template;

/** Per-category template field editor. */
final class FieldController
{
    public static function edit(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        $cid = (int) $campaign['id'];
        $category = self::category($cid, (int) $params['cid']);

        View::render('fields/edit', [
            'campaign' => $campaign,
            'tree'     => Category::tree($cid),
            'category' => $category,
            'fields'   => Template::rawFields((int) $category['id']),
            'types'    => Template::TYPES,
        ], 'app_layout');
    }

    public static function save(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        Csrf::check();
        $cid = (int) $campaign['id'];
        $category = self::category($cid, (int) $params['cid']);

        $labels = $_POST['label'] ?? [];
        $typesIn = $_POST['type'] ?? [];
        $optionsIn = $_POST['options'] ?? [];

        $fields = [];
        foreach ((array) $labels as $i => $label) {
            $fields[] = [
                'label'   => (string) $label,
                'type'    => (string) ($typesIn[$i] ?? 'text'),
                'options' => (string) ($optionsIn[$i] ?? ''),
            ];
        }
        Template::saveFields($cid, (int) $category['id'], $fields);
        Flash::set('success', 'Fields updated for “' . $category['name'] . '”.');
        redirect('/campaign/' . $cid . '/category/' . (int) $category['id'] . '/fields');
    }

    /** Force this category's fields back to the built-in defaults (overwrites). */
    public static function reset(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        Csrf::check();
        $cid = (int) $campaign['id'];
        $category = self::category($cid, (int) $params['cid']);

        if (Template::forceSeedForCategory($cid, (int) $category['id'], $category['name'])) {
            Flash::set('success', 'Fields reset to defaults for “' . $category['name'] . '”.');
        } else {
            Flash::set('error', 'No built-in defaults exist for “' . $category['name'] . '”.');
        }
        redirect('/campaign/' . $cid . '/category/' . (int) $category['id'] . '/fields');
    }

    public static function loadDefaults(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        Csrf::check();
        $cid = (int) $campaign['id'];
        $category = self::category($cid, (int) $params['cid']);

        if (Template::seedForCategory($cid, (int) $category['id'], $category['name'])) {
            Flash::set('success', 'Loaded the default fields for “' . $category['name'] . '”.');
        } else {
            Flash::set('error', 'No defaults available (or fields already exist) for “' . $category['name'] . '”.');
        }
        redirect('/campaign/' . $cid . '/category/' . (int) $category['id'] . '/fields');
    }

    private static function category(int $campaignId, int $categoryId): array
    {
        $cat = Db::run(
            'SELECT * FROM categories WHERE id = ? AND campaign_id = ?',
            [$categoryId, $campaignId]
        )->fetch();
        if (!$cat) {
            http_response_code(404);
            View::render('errors/404', [], 'app_layout');
            exit;
        }
        return $cat;
    }
}
