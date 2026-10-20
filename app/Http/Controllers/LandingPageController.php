<?php

namespace App\Http\Controllers;

use App\Enums\LeaderboardPeriod;
use App\Models\Dealer;
use App\Services\LeaderboardService;
use App\Services\PublicAwardResults;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function __invoke(Request $request, LeaderboardService $leaderboard, PublicAwardResults $awards): View
    {
        $request->validate(['period' => ['nullable', Rule::in(['week', 'month'])]]);
        // Existing weekly bookmarks now display the monthly leaderboard.
        $period = LeaderboardPeriod::Month;
        $at = CarbonImmutable::now(config('app.timezone'));

        return view('welcome', [
            'period' => $period,
            'stats' => [
                'dealers' => Dealer::where('is_active', true)->count(),
                'points' => (int) DB::query()->fromSub($leaderboard->dealers(LeaderboardPeriod::AllTime), 'dealer_totals')->sum('score'),
            ],
            'topDealers' => $leaderboard->dealers($period, $at)->limit(3)->get(),
            'latestAward' => $awards->latest(),
        ]);
    }
}
