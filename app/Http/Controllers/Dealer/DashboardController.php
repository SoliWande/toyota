<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Services\DealerDashboardData;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DealerDashboardData $data, LeaderboardService $leaderboard): View
    {
        $dealer = $data->currentDealer($request->user());
        $this->authorize('view', $dealer);

        return view('dealer.dashboard', [
            'dealer' => $dealer, 'standings' => $data->standings($dealer, $leaderboard),
            'salesCount' => $dealer->sales()->count(), 'customerCount' => $dealer->submissions()->count(),
            'recentCustomers' => $data->customers($dealer)->orderByDesc('submitted_at')->orderByDesc('id')->limit(5)->get(),
            'awards' => $data->awards($dealer)->limit(3)->get(),
        ]);
    }
}
