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
use App\Models\Page;

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
        $tree = Category::tree((int) $campaign['id']);
        $pages = Page::listForCampaign((int) $campaign['id']);

        View::render('campaigns/show', [
            'campaign' => $campaign,
            'tree'     => $tree,
            'members'  => Campaign::members((int) $campaign['id']),
            'hasPages' => count($pages) > 0,
            'firstPage'=> $pages[0] ?? null,
        ], 'app_layout');
    }
}
