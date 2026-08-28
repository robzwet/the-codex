<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Csrf;
use App\Lib\Flash;
use App\Lib\Guard;
use App\Lib\Slug;
use App\Lib\Upload;
use App\Lib\View;
use App\Lib\WikiLinks;
use App\Models\Category;
use App\Models\Page;
use App\Models\Tag;
use App\Models\Template;
use App\Models\User;

final class PageController
{
    public static function show(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        $cid = (int) $campaign['id'];
        $page = Page::find($cid, $params['slug']);
        if (!$page) {
            http_response_code(404);
            View::render('errors/404', [], 'app_layout');
            return;
        }

        // Build a typed infobox from template fields + stored meta.
        $fields = Template::fieldsFor($cid, $page['category_id'] !== null ? (int) $page['category_id'] : null);
        $metaRows = Page::meta((int) $page['id']);
        $metaMap = [];
        foreach ($metaRows as $m) {
            $metaMap[$m['meta_key']] = $m['meta_value'];
        }

        $display = [];
        $used = [];
        foreach ($fields as $f) {
            $val = $metaMap[$f['field_key']] ?? '';
            if ($val === '') {
                continue;
            }
            $type = $f['type'];
            if ($type === 'user') {
                // Stored value is a user id; show the username.
                $val = User::name((int) $val) ?? $val;
                $type = 'text';
            }
            $row = ['label' => $f['label'], 'type' => $type, 'value' => $val];
            if ($type === 'link') {
                $target = Page::findByTitle($cid, $val);
                $row['exists'] = (bool) $target;
                $row['href'] = $target
                    ? '/campaign/' . $cid . '/page/' . rawurlencode($target['slug'])
                    : '/campaign/' . $cid . '/new?title=' . rawurlencode($val);
            }
            $display[] = $row;
            $used[$f['field_key']] = true;
        }
        $leftover = [];
        foreach ($metaRows as $m) {
            if (empty($used[$m['meta_key']]) && $m['meta_value'] !== '') {
                $leftover[] = ['label' => $m['meta_key'], 'value' => $m['meta_value']];
            }
        }

        $sections = [];
        foreach (Page::sections((int) $page['id']) as $sec) {
            $sections[] = ['title' => $sec['title'], 'html' => WikiLinks::render($sec['body_html'] ?? '', $cid)];
        }

        View::render('pages/show', [
            'campaign'  => $campaign,
            'tree'      => Category::tree($cid),
            'page'      => $page,
            'sections'  => $sections,
            'bodyHtml'  => WikiLinks::render($page['body_html'] ?? '', $cid),
            'display'   => $display,
            'leftover'  => $leftover,
            'tags'      => Tag::forPage((int) $page['id']),
            'related'   => Page::incomingReferences($cid, $page),
            'authors'   => Page::authors((int) $page['id']),
            'isSession' => $isSession = Template::isSessionCategory($cid, $page['category_id'] !== null ? (int) $page['category_id'] : null),
            'neighbors' => $isSession ? Page::sessionNeighbors($cid, (int) $page['id']) : ['prev' => null, 'next' => null],
            'backlinks' => Page::backlinks((int) $page['id']),
        ], 'app_layout');
    }

    public static function createForm(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        $cid = (int) $campaign['id'];
        $categoryId = ($_GET['category'] ?? '') !== '' ? (int) $_GET['category'] : null;

        $sections = array_map(
            fn($t) => ['title' => $t, 'html' => ''],
            Template::sectionTitles($cid, $categoryId)
        );

        self::renderForm($campaign, 'create', [
            'title'       => $_GET['title'] ?? '',
            'category_id' => $categoryId,
            'slug'        => null,
        ], [], $categoryId, '', $sections);
    }

    public static function store(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        Csrf::check();
        $cid = (int) $campaign['id'];
        $categoryId = self::categoryId($_POST['category_id'] ?? null);
        $sections = self::parseSections();

        $id = Page::create(
            $cid,
            $_POST['title'] ?? '',
            $categoryId,
            Template::isSessionCategory($cid, $categoryId) ? 'note' : 'entity',
            self::buildBody($sections),
            (int) Auth::id(),
            self::collectMeta($cid, $categoryId)
        );
        Page::saveSections($id, $sections);
        Tag::setForPage($cid, $id, $_POST['tags'] ?? '');

        $page = Page::findById($id);
        Flash::set('success', 'Page created.');
        redirect('/campaign/' . $cid . '/page/' . rawurlencode($page['slug']));
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

        $cid = (int) $campaign['id'];
        $categoryId = $page['category_id'] !== null ? (int) $page['category_id'] : null;

        $values = [];
        foreach (Page::meta((int) $page['id']) as $m) {
            $values[$m['meta_key']] = $m['meta_value'];
        }

        // Existing sections, or migrate: default titles + any legacy body.
        $sections = array_map(
            fn($s) => ['title' => $s['title'], 'html' => $s['body_html'] ?? ''],
            Page::sections((int) $page['id'])
        );
        if (!$sections) {
            $sections = array_map(fn($t) => ['title' => $t, 'html' => ''], Template::sectionTitles($cid, $categoryId));
            if (trim((string) ($page['body_html'] ?? '')) !== '') {
                array_unshift($sections, ['title' => 'Notities', 'html' => $page['body_html']]);
            }
            if (!$sections) {
                $sections = [['title' => 'Notities', 'html' => $page['body_html'] ?? '']];
            }
        }

        self::renderForm(
            $campaign,
            'edit',
            $page,
            $values,
            $categoryId,
            implode(', ', Tag::forPage((int) $page['id'])),
            $sections
        );
    }

    public static function update(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        Csrf::check();
        $cid = (int) $campaign['id'];
        $page = Page::find($cid, $params['slug']);
        if (!$page) {
            http_response_code(404);
            View::render('errors/404', [], 'app_layout');
            return;
        }

        $categoryId = self::categoryId($_POST['category_id'] ?? null);
        $sections = self::parseSections();
        Page::update(
            (int) $page['id'],
            $cid,
            $_POST['title'] ?? '',
            $categoryId,
            self::buildBody($sections),
            (int) Auth::id(),
            self::collectMeta($cid, $categoryId)
        );
        Page::saveSections((int) $page['id'], $sections);
        Tag::setForPage($cid, (int) $page['id'], $_POST['tags'] ?? '');

        $fresh = Page::findById((int) $page['id']);
        Flash::set('success', 'Saved.');
        redirect('/campaign/' . $cid . '/page/' . rawurlencode($fresh['slug']));
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
        $cid = (int) $campaign['id'];
        $page = Page::find($cid, $params['slug']);
        $revision = $page ? Page::revision((int) ($_POST['revision_id'] ?? 0), (int) $page['id']) : null;
        if (!$page || !$revision) {
            Flash::set('error', 'Could not restore that revision.');
            redirect('/campaign/' . $cid);
        }

        $currentMeta = array_map(
            fn($r) => ['key' => $r['meta_key'], 'value' => $r['meta_value']],
            Page::meta((int) $page['id'])
        );

        Page::update(
            (int) $page['id'],
            $cid,
            $revision['title'],
            self::categoryId($page['category_id']),
            $revision['body_html'] ?? '',
            (int) Auth::id(),
            $currentMeta
        );
        // Rebuild sections from the restored combined body (<h2>title</h2> blocks).
        Page::saveSections((int) $page['id'], self::splitBody($revision['body_html'] ?? ''));

        Flash::set('success', 'Restored an earlier version.');
        $fresh = Page::findById((int) $page['id']);
        redirect('/campaign/' . $cid . '/page/' . rawurlencode($fresh['slug']));
    }

    // --- helpers --------------------------------------------------------------

    private static function renderForm(array $campaign, string $mode, array $page, array $values, ?int $categoryId, string $tags, array $sections): void
    {
        $cid = (int) $campaign['id'];
        View::render('pages/form', [
            'campaign'   => $campaign,
            'tree'       => Category::tree($cid),
            'categories' => Category::forCampaign($cid),
            'mode'       => $mode,
            'page'       => $page,
            'fields'     => Template::fieldsFor($cid, $categoryId),
            'values'     => $values,
            'campaignId' => $cid,
            'sections'   => $sections,
            'tags'       => $tags,
            'allTags'    => array_column(Tag::allForCampaign($cid), 'tag'),
        ], 'app_layout');
    }

    /** @return array<int,array{title:string,html:string}> */
    private static function parseSections(): array
    {
        $data = json_decode($_POST['sections_json'] ?? '', true);
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $s) {
            $title = trim((string) ($s['title'] ?? ''));
            if ($title !== '') {
                $out[] = ['title' => $title, 'html' => (string) ($s['html'] ?? '')];
            }
        }
        return $out;
    }

    /** Split a combined "<h2>title</h2>content" body back into sections. */
    private static function splitBody(string $html): array
    {
        $sections = [];
        if (preg_match_all('/<h2>(.*?)<\/h2>(.*?)(?=<h2>|$)/s', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $mm) {
                $sections[] = [
                    'title' => trim(html_entity_decode(strip_tags($mm[1]), ENT_QUOTES)),
                    'html'  => trim($mm[2]),
                ];
            }
        }
        return $sections;
    }

    /** Concatenate sections into a single body (for wikilink sync + search). */
    private static function buildBody(array $sections): string
    {
        $parts = [];
        foreach ($sections as $s) {
            $parts[] = '<h2>' . htmlspecialchars($s['title'], ENT_QUOTES) . '</h2>' . $s['html'];
        }
        return implode("\n", $parts);
    }

    private static function categoryId($value): ?int
    {
        return ($value === null || $value === '' || $value === '0') ? null : (int) $value;
    }

    /**
     * Collect infobox values from the submitted template fields (+ any custom
     * rows), handling image uploads. @return array<int,array{key:string,value:string}>
     */
    private static function collectMeta(int $campaignId, ?int $categoryId): array
    {
        $meta = [];
        $post = $_POST['field'] ?? [];
        foreach (Template::fieldsFor($campaignId, $categoryId) as $f) {
            $key = $f['field_key'];
            if ($f['type'] === 'image') {
                $value = trim((string) ($post[$key] ?? '')); // hidden field keeps existing path
                if (!empty($_POST['clear_image'][$key])) {
                    $value = '';
                }
                try {
                    $uploaded = Upload::image('image_file', $key, $campaignId);
                    if ($uploaded !== null) {
                        $value = $uploaded;
                    }
                } catch (\RuntimeException $e) {
                    Flash::set('error', $f['label'] . ': ' . $e->getMessage());
                }
            } else {
                $value = trim((string) ($post[$key] ?? ''));
            }
            if ($value !== '') {
                $meta[] = ['key' => $key, 'value' => $value];
            }
        }

        // Optional ad-hoc extra fields.
        $keys = $_POST['meta_key'] ?? [];
        $vals = $_POST['meta_value'] ?? [];
        foreach ((array) $keys as $i => $k) {
            $k = trim((string) $k);
            $v = trim((string) ($vals[$i] ?? ''));
            if ($k !== '' && $v !== '') {
                $meta[] = ['key' => $k, 'value' => $v];
            }
        }

        return $meta;
    }
}
