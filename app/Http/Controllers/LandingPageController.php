<?php

namespace App\Http\Controllers;

use App\Enums\LeaderboardPeriod;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Award;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function __invoke(Request $request, LeaderboardService $leaderboard): View
    {
        $data = $request->validate(['period' => ['nullable', Rule::in(['week', 'month'])]]);
        $period = LeaderboardPeriod::from($data['period'] ?? 'week');
        $at = CarbonImmutable::now(config('app.timezone'));

        return view('welcome', [
            'period' => $period,
            'stats' => [
                'sales' => User::where('role', UserRole::Sales->value)->where('status', UserStatus::Active->value)->count(),
                'dealers' => Dealer::where('is_active', true)->count(),
                'members' => CustomerSubmission::where('status', SubmissionStatus::Approved->value)->count(),
            ],
            'topSales' => $leaderboard->sales($period, $at)->limit(3)->get(),
            'topDealers' => $leaderboard->dealers($period, $at)->limit(3)->get(),
            'latestAward' => Award::whereNotNull('published_at')->with(['winners' => fn ($query) => $query->orderBy('rank')])
                ->orderByDesc('published_at')->orderByDesc('id')->first(),
        ]);
    }
}
