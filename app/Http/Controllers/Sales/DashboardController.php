<?php

namespace App\Http\Controllers\Sales;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, LeaderboardService $leaderboard): View
    {
        $sales = $request->user()->loadMissing('dealer');
        $counts = $sales->submissions()->toBase()
            ->select('status')->selectRaw('COUNT(*) AS total')
            ->groupBy('status')->pluck('total', 'status');

        return view('sales.dashboard', [
            'sales' => $sales,
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
