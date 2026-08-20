<?php

declare(strict_types=1);

namespace App\Lib;

use App\Models\Campaign;

final class Guard
{
    /**
     * Require the current user to be a logged-in member of the campaign.
     * Returns the campaign row, or halts with a redirect / error.
     */
    public static function campaign(string|int $id): array
    {
        Auth::requireLogin();
        $campaign = Campaign::find((int) $id);
        if (!$campaign) {
            http_response_code(404);
            View::render('errors/404', [], 'layout');
            exit;
        }
        if (!Campaign::isMember((int) $id, (int) Auth::id())) {
            http_response_code(403);
            View::render('errors/403', [], 'layout');
            exit;
        }
        return $campaign;
    }
}
