<?php

namespace App\Http\Controllers;

use App\Enums\LeaderboardPeriod;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function __invoke(Request $request, LeaderboardService $leaderboard): View
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::enum(LeaderboardPeriod::class)],
            'sales_page' => ['nullable', 'integer', 'min:1'],
            'dealers_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $period = LeaderboardPeriod::from($data['period'] ?? LeaderboardPeriod::Week->value);
        $at = CarbonImmutable::now(config('app.timezone'));
        $sales = $leaderboard->sales($period, $at)->paginate(20, ['*'], 'sales_page')->withQueryString();
        $dealers = $leaderboard->dealers($period, $at)->paginate(20, ['*'], 'dealers_page')->withQueryString();

        return view('leaderboard.index', [
            'period' => $period,
            'bounds' => $leaderboard->bounds($period, $at),
            'sales' => $sales,
            'dealers' => $dealers,
            'topSales' => $sales->currentPage() === 1 ? $sales->getCollection()->take(3) : $leaderboard->sales($period, $at)->limit(3)->get(),
            'topDealers' => $dealers->currentPage() === 1 ? $dealers->getCollection()->take(3) : $leaderboard->dealers($period, $at)->limit(3)->get(),
        ]);
    }
}
