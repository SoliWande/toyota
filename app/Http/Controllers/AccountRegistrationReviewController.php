<?php

namespace App\Http\Controllers;

use App\Actions\ReviewAccountRegistration;
use App\Http\Requests\ReviewAccountRegistrationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class AccountRegistrationReviewController extends Controller
{
    public function __invoke(ReviewAccountRegistrationRequest $request, User $account, string $action, ReviewAccountRegistration $review): RedirectResponse
    {
        $review->execute($request->user(), $account, $action, $request->validated());

        return back()->with('success', 'Đã cập nhật trạng thái tài khoản.');
    }
}
