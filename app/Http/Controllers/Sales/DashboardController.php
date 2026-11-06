<?php

namespace App\Http\Controllers\Sales;

use App\Enums\LeaderboardPeriod;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DemoSubmissionScope;
use App\Services\LeaderboardService;
use App\Services\SalesCustomerList;
use App\Services\StaffCustomerLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, LeaderboardService $leaderboard): View
    {
        $sales = $request->user()->loadMissing('dealer');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(SubmissionStatus::class)], 'tab' => ['nullable', Rule::in(['remind', 'verification', 'verified', 'all'])], 'page' => ['nullable', 'integer', 'min:1']]);
        $listing = app(SalesCustomerList::class);
        $groups = $listing->groups($sales);
        // Preserve legacy search/status callers; the B08 pill links explicitly choose their tab.
        $activeTab = $filters['tab'] ?? (filled($filters['q'] ?? null) || filled($filters['status'] ?? null) ? 'all' : 'remind');
        $customers = $sales->submissions()->with('dealer:id,slug')->select(['id', 'sales_id', 'customer_name', 'status', 'source', 'submitted_at', 'facebook_url_normalized', 'phone', 'license_plate', 'dealer_id']);
        if ($activeTab !== 'all') {
            $customers->whereIn('id', $groups[$activeTab]);
        }
        if (filled($filters['status'] ?? null)) {
            $customers->where('status', $filters['status']);
        }
        if (filled($filters['q'] ?? null)) {
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $customers->where(fn ($query) => $query->whereRaw("customer_name LIKE ? ESCAPE '!'", [$search])
                ->orWhereRaw("facebook_url LIKE ? ESCAPE '!'", [$search])->orWhereRaw("phone LIKE ? ESCAPE '!'", [$search]));
        }
        [$start, $end] = $leaderboard->bounds(LeaderboardPeriod::Month);
        $monthly = app(DemoSubmissionScope::class)->apply($sales->submissions()->getQuery())
            ->where('submitted_at', '>=', $start)->where('submitted_at', '<', $end);
        $monthCounts = (clone $monthly)->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $supplementCount = (clone $monthly)->where('status', 'pending')->whereIn('id', fn ($query) => $query->select('submission_id')->from('submission_verification_states')->where('supplement_state', 'open'))->count();
        $internal = DB::query()->fromSub($leaderboard->sales(LeaderboardPeriod::Month), 'standings')
            ->whereIn('id', User::where('dealer_id', $sales->dealer_id)->where('role', 'sales')->select('id'))->orderBy('rank')->get();
        $internalIndex = $internal->search(fn ($person) => (int) $person->id === $sales->id);
        $links = app(StaffCustomerLinks::class);
        $inviteUrl = $links->sales($sales);
        $customerRows = $customers->orderByDesc('submitted_at')->orderByDesc('id')->paginate(20)->withQueryString();
        $counts = app(DemoSubmissionScope::class)->apply($sales->submissions()->toBase())
            ->select('status')->selectRaw('COUNT(*) AS total')
            ->groupBy('status')->pluck('total', 'status');

        return view('sales.dashboard', [
            'sales' => $sales,
            'filters' => $filters, 'customers' => $customerRows, 'submissions' => $customerRows,
            'activeTab' => $activeTab, 'tabCounts' => array_map('count', $groups),
            'customerPresentation' => $listing->project($customerRows->getCollection()),
            'monthCounts' => ['approved' => (int) $monthCounts->get('approved', 0), 'pending' => max(0, (int) $monthCounts->get('pending', 0) - $supplementCount), 'supplement' => $supplementCount],
            'internalRank' => $internalIndex === false ? null : $internalIndex + 1,
            'inviteUrl' => $inviteUrl, 'inviteQr' => $inviteUrl ? $links->qr($inviteUrl) : null,
            'stats' => [
                'total' => (int) $counts->sum(),
                'approved' => (int) $counts->get(SubmissionStatus::Approved->value, 0),
                'pending' => (int) $counts->get(SubmissionStatus::Pending->value, 0),
                'rejected' => (int) $counts->get(SubmissionStatus::Rejected->value, 0),
            ],
            'recentSubmissions' => $sales->submissions()
                ->select(['id', 'customer_name', 'status', 'submitted_at'])
                ->orderByDesc('submitted_at')->orderByDesc('id')->limit(5)->get(),
            'currentRank' => $counts->get(SubmissionStatus::Approved->value, 0) > 0 ? $leaderboard->salesRank($sales->id) : null,
        ]);
    }
}
