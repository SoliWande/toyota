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
            'dealers_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $period = LeaderboardPeriod::from($data['period'] ?? LeaderboardPeriod::Month->value);
        if ($period === LeaderboardPeriod::Week) {
            $period = LeaderboardPeriod::Month;
        }
        $at = CarbonImmutable::now(config('app.timezone'));
        $dealers = $leaderboard->dealers($period, $at)->paginate(20, ['*'], 'dealers_page')
            ->appends(['period' => $period->value]);

        return view('leaderboard.index', [
            'period' => $period,
            'bounds' => $leaderboard->bounds($period, $at),
            'dealers' => $dealers,
            'topDealers' => $dealers->currentPage() === 1 ? $dealers->getCollection()->take(3) : $leaderboard->dealers($period, $at)->limit(3)->get(),
        ]);
    }
}
