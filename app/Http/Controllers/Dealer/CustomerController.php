<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\CustomerIndexRequest;
use App\Services\DealerDashboardData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(CustomerIndexRequest $request, DealerDashboardData $data): View
    {
        $dealer = $data->currentDealer($request->user());
        $filters = $request->validated();
        $query = $data->customers($dealer)->select(['id', 'dealer_id', 'sales_id', 'customer_name', 'phone', 'status', 'submitted_at']);
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }
        if (filled($filters['q'] ?? null)) {
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn ($query) => $query->whereRaw("customer_name LIKE ? ESCAPE '!'", [$search])
                ->orWhereRaw("phone LIKE ? ESCAPE '!'", [$search]));
        }

        return view('dealer.customers.index', ['dealer' => $dealer, 'filters' => $filters,
            'customers' => $query->orderByDesc('submitted_at')->orderByDesc('id')->paginate(20)->appends($request->only(['q', 'status']))]);
    }

    public function show(Request $request, int $submission, DealerDashboardData $data): View
    {
        $dealer = $data->currentDealer($request->user());
        $submission = $data->customers($dealer)->whereKey($submission)->firstOrFail();
        $this->authorize('view', $submission);

        return view('dealer.customers.show', compact('dealer', 'submission'));
    }
}
