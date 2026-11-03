<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

final class CampaignRulesController extends Controller
{
    public function __invoke(): Response
    {
        // No database, Dealer context, draft read/write, session grant or customer data.
        $sample = app()->environment(['local', 'testing']);
        $document = $sample ? require resource_path('content/campaign-rules.php') : null;

        return response()->view('campaign.rules', compact('sample', 'document'))
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
