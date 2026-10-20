<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\SalesIndexRequest;
use App\Services\DealerDashboardData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function index(SalesIndexRequest $request, DealerDashboardData $data): View
    {
        $dealer = $data->currentDealer($request->user());
        $filters = $request->validated();
        $query = $data->sales($dealer);
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }
        if (filled($filters['q'] ?? null)) {
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn ($query) => $query->whereRaw("name LIKE ? ESCAPE '!'", [$search])
                ->orWhereRaw("email LIKE ? ESCAPE '!'", [$search])->orWhereRaw("phone LIKE ? ESCAPE '!'", [$search]));
        }

        return view('dealer.sales.index', ['dealer' => $dealer, 'filters' => $filters,
            'sales' => $query->orderBy('name')->orderBy('id')->paginate(20)->appends($request->only(['q', 'status']))]);
    }

    public function show(Request $request, int $sales, DealerDashboardData $data): View
    {
        $dealer = $data->currentDealer($request->user());
        $sales = $data->sales($dealer)->whereKey($sales)->firstOrFail();
        $this->authorize('view', $sales);

        return view('dealer.sales.show', compact('dealer', 'sales'));
    }
}
