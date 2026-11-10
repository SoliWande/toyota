<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ReviewAccountRegistration;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewDealerRegistrationRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DealerRegistrationController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('reviewDealerRegistrations', User::class);

        $tab = $request->validate(['tab' => ['nullable', 'in:pending,overdue']])['tab'] ?? 'pending';
        $now = CarbonImmutable::now(config('app.timezone'));
        $manager = fn ($query) => $query->where('status', UserStatus::Active->value);
        $pending = User::where('role', UserRole::Dealer->value)->where('status', UserStatus::Pending->value);
        // Same operational clock as C01: latest account resubmission, falling back to initial registration.
        $waitingSince = "COALESCE((SELECT MAX(reviewed_at) FROM moderation_reviews WHERE sales_id = users.id AND customer_submission_id IS NULL AND to_status = 'pending'), users.created_at)";
        $overdueSales = User::where('role', UserRole::Sales->value)->where('status', UserStatus::Pending->value)
            ->select(['id', 'name', 'dealer_id'])->selectRaw($waitingSince.' AS waiting_since')
            ->whereRaw($waitingSince.' < ?', [$now->subDays(3)->format('Y-m-d H:i:s')])
            ->with(['dealer.manager' => $manager])->orderBy('waiting_since')->orderBy('id')->get();
        foreach ($overdueSales as $sales) {
            $sales->waiting_days = (int) CarbonImmutable::parse($sales->waiting_since, config('app.timezone'))->diffInDays($now);
        }

        return view('admin.dealer-registrations', [
            'tab' => $tab,
            'pendingCount' => (clone $pending)->count(),
            // Repeat the designed card for every pending account; no invented pagination controls.
            'accounts' => $tab === 'pending' ? $pending->with(['dealer.manager' => $manager])->orderBy('id')->get() : collect(),
            'overdueSales' => $overdueSales,
        ]);
    }

    public function review(ReviewDealerRegistrationRequest $request, User $account, string $action, ReviewAccountRegistration $review): JsonResponse|RedirectResponse
    {
        // This adapter is only used by the Admin route. The shared Sales/Manager controller is unchanged.
        $this->authorize('reviewDealerRegistrations', User::class);
        abort_unless($account->role === UserRole::Dealer, 403);
        $review->execute($request->user(), $account, $action, $request->validated());
        if ($request->expectsJson()) {
            return response()->json(['message' => $action === 'approve'
                ? 'Đã duyệt '.$account->name.' làm quản lý '.$account->dealer->name : null]);
        }

        return back()->with('success', 'Đã cập nhật trạng thái tài khoản.');
    }
}
