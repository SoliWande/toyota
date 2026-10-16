<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateDealerAccount;
use App\Actions\ReviewDealerAccount;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateDealerAccountRequest;
use App\Http\Requests\Admin\DealerAccountIndexRequest;
use App\Http\Requests\Admin\ReviewDealerAccountRequest;
use App\Http\Requests\Admin\UpdateDealerAccountRequest;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DealerAccountController extends Controller
{
    public function index(DealerAccountIndexRequest $request): View
    {
        $filters = $request->validated();
        $query = User::where('role', UserRole::Dealer->value)->with('dealer');
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

        return view('admin.dealer-accounts.index', [
            'accounts' => $query->orderByDesc('id')->paginate(20)->withQueryString(),
            'dealers' => Dealer::orderBy('name')->get(['id', 'name']),
            'statuses' => UserStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.dealer-accounts.create', [
            'dealers' => Dealer::where('is_active', true)->whereDoesntHave('loginAccount')->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(CreateDealerAccountRequest $request, CreateDealerAccount $create): RedirectResponse
    {
        $account = $create->execute($request->user(), $request->validated());

        return redirect()->route('admin.dealer-accounts.show', $account)->with('success', 'Đã tạo tài khoản đại lý. Vui lòng kích hoạt để cho phép sử dụng hệ thống.');
    }

    public function show(User $account): View
    {
        $this->authorize('view', $account);

        return view('admin.dealer-accounts.show', [
            'account' => $account->load(['dealer', 'reviewer']),
            'reviews' => $account->moderationReviews()->with('reviewer')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function review(ReviewDealerAccountRequest $request, User $account, string $action, ReviewDealerAccount $review): RedirectResponse
    {
        $review->execute($request->user(), $account, $action, $request->validated());

        return back()->with('success', 'Đã cập nhật trạng thái tài khoản đại lý.');
    }

    public function edit(User $account): View
    {
        $this->authorize('update', $account);

        return view('admin.dealer-accounts.edit', ['account' => $account->loadMissing('dealer')]);
    }

    public function update(UpdateDealerAccountRequest $request, User $account): RedirectResponse
    {
        DB::transaction(function () use ($request, $account) {
            $locked = User::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $this->authorize('update', $locked);
            $locked->name = $request->validated('name');
            $locked->phone = $request->validated('phone');
            $locked->save();
        });

        return redirect()->route('admin.dealer-accounts.show', $account)->with('success', 'Đã cập nhật tài khoản đại lý.');
    }
}
