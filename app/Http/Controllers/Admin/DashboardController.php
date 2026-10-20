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
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(status = ?), 0) AS approved', [SubmissionStatus::Approved->value])
            ->selectRaw('COALESCE(SUM(status = ?), 0) AS pending', [SubmissionStatus::Pending->value])
            ->selectRaw('COALESCE(SUM(submitted_at >= ? AND submitted_at < ?), 0) AS week', [
                $weekStart->format('Y-m-d H:i:s'), $weekEnd->format('Y-m-d H:i:s'),
            ])
            ->selectRaw('COALESCE(SUM(submitted_at >= ? AND submitted_at < ?), 0) AS month', [
                $monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s'),
            ])->first();

        $dealerCounts = Dealer::query()->selectRaw('COUNT(*) AS total, COALESCE(SUM(is_active = 1), 0) AS active')->first();
        $topSales = $leaderboard->sales(LeaderboardPeriod::Month, $now)->limit(3)->get();
        $salesAccounts = $topSales->isEmpty() ? collect() : User::whereIn('id', $topSales->pluck('id'))
            ->get(['id', 'avatar_path', 'dealer_id', 'status', 'public_slug']);
        $salesAvatars = $salesAccounts->pluck('avatar_path', 'id')->map(fn ($path) => (bool) $path);

        return view('admin.dashboard', [
            'stats' => [
                'dealers' => (int) $dealerCounts->total,
                'active_dealers' => (int) $dealerCounts->active,
                'sales' => (int) $salesCounts->sum(),
                'submissions' => (int) $submissionCounts->total,
                'active_sales' => (int) $salesCounts->get(UserStatus::Active->value, 0),
                'pending_sales' => (int) $salesCounts->get(UserStatus::Pending->value, 0),
                'approved_submissions' => (int) $submissionCounts->approved,
                'pending_submissions' => (int) $submissionCounts->pending,
                'week_submissions' => (int) $submissionCounts->week,
                'month_submissions' => (int) $submissionCounts->month,
            ],
            'statLinks' => [
                'dealers' => route('admin.dealers.index'),
                'active_dealers' => route('admin.dealers.index', ['status' => 'active']),
                'sales' => route('admin.sales.index'),
                'active_sales' => route('admin.sales.index', ['status' => 'active']),
                'pending_sales' => route('admin.sales.index', ['status' => 'pending']),
                // The review list defaults to pending unless status is explicitly present.
                'submissions' => route('admin.submissions.index', ['status' => '']),
                'approved_submissions' => route('admin.submissions.index', ['status' => 'approved']),
                'pending_submissions' => route('admin.submissions.index', ['status' => 'pending']),
                'week_submissions' => route('admin.submissions.index', [
                    'status' => '', 'submitted_from' => $weekStart->toDateString(), 'submitted_to' => $weekEnd->subDay()->toDateString(),
                ]),
                'month_submissions' => route('admin.submissions.index', [
                    'status' => '', 'submitted_from' => $monthStart->toDateString(), 'submitted_to' => $monthEnd->subDay()->toDateString(),
                ]),
            ],
            'recentPendingSubmissions' => CustomerSubmission::query()
                ->select(['id', 'sales_id', 'dealer_id', 'customer_name', 'submitted_at'])
                ->where('status', SubmissionStatus::Pending->value)
                ->with(['sales:id,name,dealer_id', 'dealer:id,name'])
                ->orderByDesc('submitted_at')->orderByDesc('id')->limit(5)->get(),
            'topSales' => $topSales,
            'salesAvatars' => $salesAvatars,
            'salesDealerIds' => $salesAccounts->pluck('dealer_id', 'id'),
            'salesPublicSlugs' => $salesAccounts->filter(fn ($sales) => $sales->status === UserStatus::Active && $sales->public_slug)
                ->pluck('public_slug', 'id'),
            'topDealers' => $leaderboard->dealers(LeaderboardPeriod::Month, $now)->limit(3)->get(),
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
        ]);
    }
}
