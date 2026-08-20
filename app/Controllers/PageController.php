<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Csrf;
use App\Lib\Flash;
use App\Lib\Guard;
use App\Lib\View;
use App\Lib\WikiLinks;
use App\Models\Category;
use App\Models\Page;

final class PageController
{
    public static function show(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        $page = Page::find((int) $campaign['id'], $params['slug']);
        if (!$page) {
            http_response_code(404);
            View::render('errors/404', [], 'app_layout');
            return;
        }

        View::render('pages/show', [
            'campaign'  => $campaign,
            'tree'      => Category::tree((int) $campaign['id']),
            'page'      => $page,
            'bodyHtml'  => WikiLinks::render($page['body_html'] ?? '', (int) $campaign['id']),
            'meta'      => Page::meta((int) $page['id']),
            'backlinks' => Page::backlinks((int) $page['id']),
        ], 'app_layout');
    }

    public static function createForm(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        View::render('pages/form', [
            'campaign'    => $campaign,
            'tree'        => Category::tree((int) $campaign['id']),
            'categories'  => Category::forCampaign((int) $campaign['id']),
            'mode'        => 'create',
            'page'        => ['title' => $_GET['title'] ?? '', 'body_html' => '', 'category_id' => $_GET['category'] ?? null, 'kind' => 'entity'],
            'meta'        => [],
        ], 'app_layout');
    }

    public static function store(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        Csrf::check();

        $id = Page::create(
            (int) $campaign['id'],
            $_POST['title'] ?? '',
            self::categoryId($_POST['category_id'] ?? null),
            in_array($_POST['kind'] ?? 'entity', ['note', 'entity'], true) ? $_POST['kind'] : 'entity',
            $_POST['body_html'] ?? '',
            (int) Auth::id(),
            self::parseMeta()
        );

        $page = Page::findById($id);
        Flash::set('success', 'Page created.');
        redirect('/campaign/' . $campaign['id'] . '/page/' . rawurlencode($page['slug']));
    }

    public static function editForm(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        $page = Page::find((int) $campaign['id'], $params['slug']);
        if (!$page) {
            http_response_code(404);
            View::render('errors/404', [], 'app_layout');
            return;
        }

        View::render('pages/form', [
            'campaign'   => $campaign,
            'tree'       => Category::tree((int) $campaign['id']),
            'categories' => Category::forCampaign((int) $campaign['id']),
            'mode'       => 'edit',
            'page'       => $page,
            'meta'       => Page::meta((int) $page['id']),
        ], 'app_layout');
    }

    public static function update(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        Csrf::check();
        $page = Page::find((int) $campaign['id'], $params['slug']);
        if (!$page) {
            http_response_code(404);
            View::render('errors/404', [], 'app_layout');
            return;
        }

        Page::update(
            (int) $page['id'],
            (int) $campaign['id'],
            $_POST['title'] ?? '',
            self::categoryId($_POST['category_id'] ?? null),
            $_POST['body_html'] ?? '',
            (int) Auth::id(),
            self::parseMeta()
        );

        $fresh = Page::findById((int) $page['id']);
        Flash::set('success', 'Saved.');
        redirect('/campaign/' . $campaign['id'] . '/page/' . rawurlencode($fresh['slug']));
    }

    public static function delete(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        Csrf::check();
        $page = Page::find((int) $campaign['id'], $params['slug']);
        if ($page) {
            Page::delete((int) $page['id'], (int) $campaign['id']);
            Flash::set('success', 'Page deleted.');
        }
        redirect('/campaign/' . $campaign['id']);
    }

    public static function history(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        $page = Page::find((int) $campaign['id'], $params['slug']);
        if (!$page) {
            http_response_code(404);
            View::render('errors/404', [], 'app_layout');
            return;
        }

        View::render('pages/history', [
            'campaign'  => $campaign,
            'tree'      => Category::tree((int) $campaign['id']),
            'page'      => $page,
            'revisions' => Page::revisions((int) $page['id']),
        ], 'app_layout');
    }

    public static function restore(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        Csrf::check();
        $page = Page::find((int) $campaign['id'], $params['slug']);
        $revision = $page ? Page::revision((int) ($_POST['revision_id'] ?? 0), (int) $page['id']) : null;
        if (!$page || !$revision) {
            Flash::set('error', 'Could not restore that revision.');
            redirect('/campaign/' . $campaign['id']);
        }

        // Preserve current infobox meta (revisions only snapshot title + body).
        $currentMeta = array_map(
            fn($r) => ['key' => $r['meta_key'], 'value' => $r['meta_value']],
            Page::meta((int) $page['id'])
        );

        Page::update(
            (int) $page['id'],
            (int) $campaign['id'],
            $revision['title'],
            self::categoryId($page['category_id']),
            $revision['body_html'] ?? '',
            (int) Auth::id(),
            $currentMeta
        );

        Flash::set('success', 'Restored an earlier version.');
        $fresh = Page::findById((int) $page['id']);
        redirect('/campaign/' . $campaign['id'] . '/page/' . rawurlencode($fresh['slug']));
    }

    private static function categoryId($value): ?int
    {
        return ($value === null || $value === '' || $value === '0') ? null : (int) $value;
    }

    /** @return array<int,array{key:string,value:string}> */
    private static function parseMeta(): array
    {
        $keys = $_POST['meta_key'] ?? [];
        $values = $_POST['meta_value'] ?? [];
        $meta = [];
        foreach ((array) $keys as $i => $key) {
            $meta[] = ['key' => (string) $key, 'value' => (string) ($values[$i] ?? '')];
        }
        return $meta;
    }
}
