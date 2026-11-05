<?php

namespace App\Http\Controllers\Dealer;

use App\Enums\LeaderboardPeriod;
use App\Http\Controllers\Controller;
use App\Services\DealerDashboardData;
use App\Services\DemoSubmissionScope;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DealerDashboardData $data, LeaderboardService $leaderboard): View
    {
        $dealer = $data->currentDealer($request->user());
        $this->authorize('view', $dealer);

        return view('dealer.dashboard', [
            'dealer' => $dealer, 'standings' => $data->standings($dealer, $leaderboard),
            'salesCount' => $dealer->sales()->count(),
            'customerCount' => app(DemoSubmissionScope::class)->apply($dealer->submissions())->count(),
            'recentCustomers' => $data->customers($dealer)->orderByDesc('submitted_at')->orderByDesc('id')->limit(5)->get(),
            'awards' => $data->awards($dealer)->limit(3)->get(),
            'pendingSales' => $dealer->sales()->where('status', 'pending')->orderBy('id')->limit(10)->get(),
            'pendingSalesCount' => $dealer->sales()->where('status', 'pending')->count(),
            'staffStandings' => DB::query()->fromSub($leaderboard->sales(LeaderboardPeriod::Month), 'ranked')
                ->whereIn('id', $dealer->sales()->select('id'))->limit(5)->get(),
        ]);
    }
}
