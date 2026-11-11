<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ReviewSalesAccount;
use App\Enums\LeaderboardPeriod;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewSalesRequest;
use App\Http\Requests\Admin\SalesIndexRequest;
use App\Http\Requests\Admin\UpdateSalesRequest;
use App\Models\Dealer;
use App\Models\User;
use App\Services\DemoSubmissionScope;
use App\Services\LeaderboardService;
use App\Services\SalesAvatars;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function index(SalesIndexRequest $request, LeaderboardService $leaderboard): View
    {
        $filters = $request->validated();
        $base = User::where('role', UserRole::Sales->value);
        $counts = [
            'active' => (clone $base)->where('status', UserStatus::Active->value)->count(),
            'blocked' => (clone $base)->where('status', UserStatus::Blocked->value)->count(),
        ];
        // Keep explicit legacy pending/rejected filters; C05 defaults to active Sales.
        $status = $filters['status'] ?? UserStatus::Active->value;
        $query = (clone $base)->with('dealer')->where('status', $status)
            ->select('users.*')->leftJoinSub($leaderboard->salesScores(...$leaderboard->bounds(LeaderboardPeriod::Month)), 'monthly_sales', 'monthly_sales.id', '=', 'users.id')
            ->addSelect(DB::raw('COALESCE(monthly_sales.score, 0) as month_score'))
            ->withCount(['submissions as invited_count' => fn ($query) => app(DemoSubmissionScope::class)->apply($query)
                ->whereColumn('customer_submissions.dealer_id', 'users.dealer_id')]);
        if (! empty($filters['dealer_id'])) {
            $query->where('users.dealer_id', $filters['dealer_id']);
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            // Escape SQL LIKE wildcards so search text is treated literally.
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(function ($query) use ($search) {
                $query->whereRaw("users.name LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("users.email LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("users.phone LIKE ? ESCAPE '!'", [$search]);
            });
        }

        return view('admin.sales.index', [
            'sales' => $query->orderByDesc('users.id')->paginate(20)->withQueryString(),
            'dealers' => Dealer::orderBy('name')->get(['id', 'name']),
            'statuses' => UserStatus::cases(),
            'filters' => $filters,
            'counts' => $counts,
            'activeStatus' => $status,
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

        return back()->with('success', $action === 'block'
            ? 'Đã khoá '.$sales->name.' · link mời hết hiệu lực'
            : 'Đã cập nhật trạng thái tài khoản Sales.')
            ->with('sales_blocked', $action === 'block');
    }

    public function edit(User $sales): View
    {
        $this->authorize('update', $sales);

        return view('admin.sales.edit', ['sales' => $sales, 'dealers' => Dealer::orderBy('name')->get()]);
    }

    public function update(UpdateSalesRequest $request, User $sales, SalesAvatars $avatars): RedirectResponse
    {
        $avatar = $request->hasFile('avatar') ? $avatars->store($request->file('avatar')->getRealPath()) : null;
        try {
            DB::transaction(function () use ($request, $sales, $avatar) {
                $locked = User::whereKey($sales->id)->lockForUpdate()->firstOrFail();
                $this->authorize('update', $locked);
                $locked->name = $request->validated('name');
                $locked->phone = $request->validated('phone');
                $locked->dealer_id = $request->validated('dealer_id');
                if ($avatar !== null) {
                    $previous = $locked->avatar_path;
                    $locked->avatar_path = $avatar;
                    if ($previous) {
                        DB::afterCommit(fn () => rescue(fn () => Storage::disk('sales_avatars')->delete($previous)));
                    }
                }
                $locked->save();
            });
        } catch (\Throwable $error) {
            if ($avatar !== null) {
                Storage::disk('sales_avatars')->delete($avatar);
            }
            throw $error;
        }

        return redirect()->route('admin.sales.show', $sales)->with('success', 'Đã cập nhật Sales. Thành tích lịch sử giữ nguyên đại lý.');
    }
}
