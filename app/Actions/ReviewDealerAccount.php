<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Models\ModerationReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReviewDealerAccount
{
    public function execute(User $admin, User $account, string $action, array $data): void
    {
        DB::transaction(function () use ($admin, $account, $action, $data) {
            $admin = User::whereKey($admin->id)->lockForUpdate()->firstOrFail();
            $account = User::whereKey($account->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($admin)->authorize('moderate', $account);

            [$from, $to] = match ($action) {
                'activate' => [[UserStatus::Pending, UserStatus::Rejected, UserStatus::Blocked], UserStatus::Active],
                'approve' => [[UserStatus::Pending], UserStatus::Active],
                'reject' => [[UserStatus::Pending], UserStatus::Rejected],
                'block' => [[UserStatus::Active], UserStatus::Blocked],
                'reactivate' => [[UserStatus::Rejected, UserStatus::Blocked], UserStatus::Active],
                default => throw ValidationException::withMessages(['action' => 'Thao tác không hợp lệ.']),
            };

            if (! in_array($account->status, $from, true)) {
                throw ValidationException::withMessages(['action' => 'Trạng thái tài khoản đã thay đổi hoặc không phù hợp với thao tác. Vui lòng kiểm tra lại.']);
            }

            $from = $account->status;

            $reason = $action === 'reject' ? ($data['rejection_reason'] ?? null) : null;
            $time = now();
            $note = $data['admin_note'] ?? null;
            $account->forceFill([
                'status' => $to,
                'reviewed_by' => $admin->id,
                'reviewed_at' => $time,
                'rejection_reason' => $reason,
                'admin_note' => $note,
            ])->save();

            $review = new ModerationReview;
            $review->forceFill([
                'account_user_id' => $account->id,
                'reviewed_by' => $admin->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'rejection_reason' => $reason,
                'admin_note' => $note,
                'reviewed_at' => $time,
            ])->save();
        });
    }
}
