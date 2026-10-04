<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ReviewSalesAccount;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewSalesRequest;
use App\Http\Requests\Admin\SalesIndexRequest;
use App\Http\Requests\Admin\UpdateSalesRequest;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function index(SalesIndexRequest $request): View
    {
        $filters = $request->validated();
        $query = User::where('role', UserRole::Sales->value)->with('dealer');
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['dealer_id'])) {
            $query->where('dealer_id', $filters['dealer_id']);
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            // Escape SQL LIKE wildcards so search text is treated literally.
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(function ($query) use ($search) {
                $query->whereRaw("name LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("phone LIKE ? ESCAPE '!'", [$search]);
            });
        }

        return view('admin.sales.index', [
            'sales' => $query->orderByDesc('id')->paginate(20)->withQueryString(),
            'dealers' => Dealer::orderBy('name')->get(['id', 'name']),
            'statuses' => UserStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function show(User $sales): View
    {
        $this->authorize('view', $sales);

        return view('admin.sales.show', [
            'sales' => $sales->load(['dealer', 'reviewer']),
            'reviews' => $sales->moderationReviews()->with('reviewer')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function review(ReviewSalesRequest $request, User $sales, string $action, ReviewSalesAccount $review): RedirectResponse
    {
        $review->execute($request->user(), $sales, $action, $request->validated());

        return back()->with('success', 'Đã cập nhật trạng thái tài khoản Sales.');
    }

    public function edit(User $sales): View
    {
        $this->authorize('update', $sales);

        return view('admin.sales.edit', ['sales' => $sales, 'dealers' => Dealer::orderBy('name')->get()]);
    }

    public function update(UpdateSalesRequest $request, User $sales): RedirectResponse
    {
        DB::transaction(function () use ($request, $sales) {
            $locked = User::whereKey($sales->id)->lockForUpdate()->firstOrFail();
            $this->authorize('update', $locked);
            $locked->name = $request->validated('name');
            $locked->phone = $request->validated('phone');
            $locked->dealer_id = $request->validated('dealer_id');
            $locked->save();
        });

        return redirect()->route('admin.sales.show', $sales)->with('success', 'Đã cập nhật Sales. Thành tích lịch sử giữ nguyên đại lý.');
    }
}
