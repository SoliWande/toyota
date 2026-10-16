<?php

namespace App\Http\Controllers\Dealer;

use App\Enums\LeaderboardPeriod;
use App\Http\Controllers\Controller;
use App\Models\CustomerSubmission;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, LeaderboardService $leaderboard): View
    {
        $this->authorize('viewAny', CustomerSubmission::class);
        $account = $request->user()->loadMissing('dealer');
        $counts = $account->submissions()->toBase()->select('status')->selectRaw('COUNT(*) AS total')
            ->groupBy('status')->pluck('total', 'status');
        $now = CarbonImmutable::now(config('app.timezone'));
        $standings = [];
        foreach (LeaderboardPeriod::cases() as $period) {
            $standings[$period->value] = $leaderboard->dealerStanding($account->dealer_id, $period, $now);
        }

        return view('dealer.dashboard', [
            'account' => $account,
            'stats' => ['total' => (int) $counts->sum(), 'pending' => (int) $counts->get('pending', 0),
                'approved' => (int) $counts->get('approved', 0), 'rejected' => (int) $counts->get('rejected', 0)],
            'standings' => $standings,
            'recentSubmissions' => $account->submissions()->select(['id', 'dealer_id', 'customer_name', 'status', 'submitted_at'])
                ->orderByDesc('submitted_at')->orderByDesc('id')->limit(5)->get(),
        ]);
    }
}
