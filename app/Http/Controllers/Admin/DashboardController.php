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
use App\Services\DemoSubmissionScope;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, LeaderboardService $leaderboard): View
    {
        $ranking = $request->validate(['ranking' => ['nullable', 'in:dealers,sales']])['ranking'] ?? 'dealers';
        $now = CarbonImmutable::now(config('app.timezone'));
        [$weekStart, $weekEnd] = $leaderboard->bounds(LeaderboardPeriod::Week, $now);
        [$monthStart, $monthEnd] = $leaderboard->bounds(LeaderboardPeriod::Month, $now);
        $salesCounts = User::query()->where('role', UserRole::Sales->value)
            ->select('status')->selectRaw('COUNT(*) AS total')
            ->groupBy('status')->pluck('total', 'status');
        $submissionCounts = app(DemoSubmissionScope::class)->apply(CustomerSubmission::query()->toBase())
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(status = ?), 0) AS approved', [SubmissionStatus::Approved->value])
            ->selectRaw('COALESCE(SUM(status = ?), 0) AS pending', [SubmissionStatus::Pending->value])
            ->selectRaw('COALESCE(SUM(status = ? AND submitted_at >= ? AND submitted_at < ?), 0) AS approved_month', [
                SubmissionStatus::Approved->value, $monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s'),
            ])
            ->selectRaw('COALESCE(SUM(submitted_at >= ? AND submitted_at < ?), 0) AS week', [
                $weekStart->format('Y-m-d H:i:s'), $weekEnd->format('Y-m-d H:i:s'),
            ])
            ->selectRaw('COALESCE(SUM(submitted_at >= ? AND submitted_at < ?), 0) AS month', [
                $monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s'),
            ])->first();

        $dealerCounts = Dealer::query()->selectRaw('COUNT(*) AS total, COALESCE(SUM(is_active = 1), 0) AS active')->first();
        $topSales = $leaderboard->sales(LeaderboardPeriod::Month, $now)->limit(3)->get();
        // Operational queues intentionally include demo records, as do the review routes.
        $queue = CustomerSubmission::where('status', SubmissionStatus::Pending->value)
            ->selectRaw('COUNT(*) AS total, MIN(submitted_at) AS oldest')
            ->selectRaw('COALESCE(SUM(submitted_at < ?), 0) AS overdue', [$now->subHours(48)->format('Y-m-d H:i:s')])->first();
        $accounts = User::query()->where('status', UserStatus::Pending->value)
            ->selectRaw('COALESCE(SUM(role = ?), 0) AS managers', [UserRole::Dealer->value])
            ->selectRaw("COALESCE(SUM(role = ? AND COALESCE((SELECT MAX(reviewed_at) FROM moderation_reviews WHERE sales_id = users.id AND customer_submission_id IS NULL AND to_status = 'pending'), created_at) < ?), 0) AS overdue_sales", [
                UserRole::Sales->value, $now->subDays(3)->format('Y-m-d H:i:s'),
            ])->first();
        // Unqualified by month in C01: count all-time approved customers by immutable dealer snapshot.
        $qualifiedDealers = app(DemoSubmissionScope::class)->apply(CustomerSubmission::query()->toBase())
            ->where('status', SubmissionStatus::Approved->value)->whereNotNull('dealer_id')
            ->select('dealer_id')->groupBy('dealer_id')->havingRaw('COUNT(*) >= 5');
        $oldest = $queue->oldest ? CarbonImmutable::parse($queue->oldest, config('app.timezone')) : null;

        return view('admin.dashboard', [
            'now' => $now,
            'ranking' => $ranking,
            'overview' => [
                'pending' => (int) $queue->total,
                'oldest' => $oldest,
                'oldest_days' => $oldest ? (int) max(0, $oldest->diffInDays($now)) : null,
                'overdue' => (int) $queue->overdue,
                'pending_managers' => (int) $accounts->managers,
                'overdue_sales' => (int) $accounts->overdue_sales,
                'approved_month' => (int) $submissionCounts->approved_month,
                'qualified_dealers' => Dealer::query()->whereIn('id', $qualifiedDealers)->count(),
            ],
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
                ->select(['id', 'sales_id', 'dealer_id', 'customer_name', 'phone', 'source', 'submitted_at'])
                ->where('status', SubmissionStatus::Pending->value)
                ->with(['sales:id,name,dealer_id', 'dealer:id,name'])
                ->orderByDesc('submitted_at')->orderByDesc('id')->limit(4)->get(),
            'topSales' => $topSales,
            'topDealers' => $leaderboard->dealers(LeaderboardPeriod::Month, $now)->limit(3)->get(),
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
        ]);
    }
}
