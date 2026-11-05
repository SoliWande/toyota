<?php

namespace App\Http\Controllers;

use App\Actions\ReviewAccountRegistration;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountStatusController extends Controller
{
    public function resubmit(Request $request, ReviewAccountRegistration $review): RedirectResponse
    {
        $review->resubmit($request->user());

        return redirect()->route('account.status')->with('success', 'Đã gửi lại hồ sơ để xét duyệt.');
    }

    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()->status === UserStatus::Active) {
            return redirect()->route($request->user()->homeRoute());
        }

        $account = $request->user()->load('dealer', 'reviewer');
        $sentAt = $account->moderationReviews()->where('from_status', 'rejected')->where('to_status', 'pending')
            ->where('reviewed_by', $account->id)->latest('id')->value('reviewed_at') ?? $account->created_at;
        $reasonPublic = $account->reviewer && (($account->role === UserRole::Sales && $account->reviewer->role === UserRole::Dealer)
            || ($account->role === UserRole::Dealer && $account->reviewer->role === UserRole::Admin));

        return view('auth.account-status', compact('account', 'sentAt', 'reasonPublic'));
    }
}
