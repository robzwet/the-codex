<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Auth;
use App\Lib\Csrf;
use App\Lib\Flash;
use App\Lib\Guard;
use App\Lib\View;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Dashboard;
use App\Models\Page;
use App\Models\Template;

final class CampaignController
{
    public static function dashboard(): void
    {
        Auth::requireLogin();
        View::render('campaigns/dashboard', [
            'campaigns' => Campaign::forUser((int) Auth::id()),
        ], 'app_layout');
    }

    public static function create(): void
    {
        Auth::requireLogin();
        Csrf::check();
        $id = Campaign::create($_POST['name'] ?? '', $_POST['description'] ?? '', (int) Auth::id());
        Flash::set('success', 'Campaign created. Start writing!');
        redirect('/campaign/' . $id);
    }

    public static function join(): void
    {
        Auth::requireLogin();
        Csrf::check();
        $campaign = Campaign::findByInvite($_POST['invite_code'] ?? '');
        if (!$campaign) {
            Flash::set('error', 'No campaign found for that invite code.');
            redirect('/dashboard');
        }
        Campaign::join((int) $campaign['id'], (int) Auth::id());
        Flash::set('success', 'Joined "' . $campaign['name'] . '".');
        redirect('/campaign/' . $campaign['id']);
    }

    /** Two-pane campaign view; opens the first page or a welcome screen. */
    public static function show(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        $cid = (int) $campaign['id'];
        $tree = Category::tree($cid);
        $pages = Page::listForCampaign($cid);

        View::render('campaigns/show', [
            'campaign'  => $campaign,
            'tree'      => $tree,
            'members'   => Campaign::members($cid),
            'hasPages'  => count($pages) > 0,
            'firstPage' => $pages[0] ?? null,
            'hasFields' => Template::countForCampaign($cid) > 0,
        ], 'app_layout');
    }

    /** Live campaign hub: party, quests, sessions, living enemies, items. */
    public static function dashboardView(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        $cid = (int) $campaign['id'];
        View::render('campaigns/dashboard_view', [
            'campaign' => $campaign,
            'tree'     => Category::tree($cid),
            'data'     => Dashboard::build($cid),
        ], 'app_layout');
    }

    /** Seed default field templates for a pre-existing campaign's categories. */
    public static function seedTemplates(array $params): void
    {
        $campaign = Guard::campaign($params['id']);
        Csrf::check();
        $cid = (int) $campaign['id'];
        Category::ensureDefaults($cid);      // add missing default categories (e.g. Quests)
        $n = Template::seedCampaign($cid);   // seed fields on categories that have none
        Flash::set('success', "Field templates ready ({$n} categories seeded).");
        redirect('/campaign/' . $cid);
    }
}
