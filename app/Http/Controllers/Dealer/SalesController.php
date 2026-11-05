<?php

namespace App\Http\Controllers\Dealer;

use App\Enums\LeaderboardPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\SalesIndexRequest;
use App\Services\DealerDashboardData;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function index(SalesIndexRequest $request, DealerDashboardData $data, LeaderboardService $leaderboard): View
    {
        $dealer = $data->currentDealer($request->user());
        $filters = $request->validated();
        // B10 opens the active staff tab. Explicit status= keeps the legacy all-status list.
        if (! $request->has('status')) {
            $filters['status'] = 'active';
        }
        $query = $data->sales($dealer)->leftJoinSub($leaderboard->sales(LeaderboardPeriod::Month), 'standing', 'standing.id', '=', 'users.id')
            ->select(array_map(fn ($field) => 'users.'.$field, ['id', 'dealer_id', 'role', 'name', 'email', 'phone', 'status', 'avatar_path', 'public_slug', 'created_at']))
            ->selectRaw('COALESCE(standing.score,0) AS monthly_score')
            ->withMax(['submissions' => fn ($query) => $query->where('dealer_id', $dealer->id)], 'submitted_at');
        if (filled($filters['status'] ?? null)) {
            $query->where('users.status', $filters['status']);
        }
        if (filled($filters['q'] ?? null)) {
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn ($query) => $query->whereRaw("users.name LIKE ? ESCAPE '!'", [$search])
                ->orWhereRaw("users.email LIKE ? ESCAPE '!'", [$search])->orWhereRaw("users.phone LIKE ? ESCAPE '!'", [$search]));
        }

        return view('dealer.sales.index', ['dealer' => $dealer, 'filters' => $filters,
            'statusCounts' => $dealer->sales()->select('status')->selectRaw('COUNT(*) AS count')->groupBy('status')->pluck('count', 'status'),
            'pendingSales' => $dealer->sales()->where('status', 'pending')->orderBy('id')->limit(10)->get(),
            'sales' => $query->orderBy('users.name')->orderBy('users.id')->paginate(20)->appends($request->only(['q', 'status']))]);
    }

    public function show(Request $request, int $sales, DealerDashboardData $data): View
    {
        $dealer = $data->currentDealer($request->user());
        $sales = $data->sales($dealer)->whereKey($sales)->firstOrFail();
        $this->authorize('view', $sales);

        return view('dealer.sales.show', compact('dealer', 'sales'));
    }
}
