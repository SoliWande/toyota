<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeaderboardPeriod;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(LeaderboardService $leaderboard): View
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        [$weekStart, $weekEnd] = $leaderboard->bounds(LeaderboardPeriod::Week, $now);
        [$monthStart, $monthEnd] = $leaderboard->bounds(LeaderboardPeriod::Month, $now);
        $salesCounts = User::query()->where('role', UserRole::Sales->value)
            ->select('status')->selectRaw('COUNT(*) AS total')
            ->groupBy('status')->pluck('total', 'status');
        $submissionCounts = CustomerSubmission::query()->toBase()
            ->selectRaw('COALESCE(SUM(status = ?), 0) AS approved', [SubmissionStatus::Approved->value])
            ->selectRaw('COALESCE(SUM(status = ?), 0) AS pending', [SubmissionStatus::Pending->value])
            ->selectRaw('COALESCE(SUM(submitted_at >= ? AND submitted_at < ?), 0) AS week', [
                $weekStart->format('Y-m-d H:i:s'), $weekEnd->format('Y-m-d H:i:s'),
            ])
            ->selectRaw('COALESCE(SUM(submitted_at >= ? AND submitted_at < ?), 0) AS month', [
                $monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s'),
            ])->first();

        return view('admin.dashboard', [
            'stats' => [
                'dealers' => Dealer::query()->count(),
                'active_sales' => (int) $salesCounts->get(UserStatus::Active->value, 0),
                'pending_sales' => (int) $salesCounts->get(UserStatus::Pending->value, 0),
                'approved_submissions' => (int) $submissionCounts->approved,
                'pending_submissions' => (int) $submissionCounts->pending,
                'week_submissions' => (int) $submissionCounts->week,
                'month_submissions' => (int) $submissionCounts->month,
            ],
            'recentPendingSubmissions' => CustomerSubmission::query()
                ->select(['id', 'sales_id', 'dealer_id', 'customer_name', 'submitted_at'])
                ->where('status', SubmissionStatus::Pending->value)
                ->with(['sales:id,name,dealer_id', 'dealer:id,name'])
                ->orderByDesc('submitted_at')->orderByDesc('id')->limit(5)->get(),
            'topSales' => $leaderboard->sales(LeaderboardPeriod::Week, $now)->limit(3)->get(),
            'topDealers' => $leaderboard->dealers(LeaderboardPeriod::Week, $now)->limit(3)->get(),
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
        ]);
    }
}
