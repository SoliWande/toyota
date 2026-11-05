<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Dealer;
use App\Models\ModerationReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ReviewAccountRegistration
{
    public function execute(User $actor, User $target, string $action, array $data = []): void
    {
        DB::transaction(function () use ($actor, $target, $action, $data) {
            $dealer = Dealer::whereKey($target->dealer_id)->lockForUpdate()->firstOrFail();
            $users = User::whereIn('id', [$actor->id, $target->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $users->get($actor->id);
            $target = $users->get($target->id);
            abort_unless($actor && $target, 403);
            Gate::forUser($actor)->authorize(in_array($action, ['approve', 'reject'], true) ? 'reviewRegistration' : 'manageStaff', $target);
            if ($target->dealer_id !== $dealer->id || ! $dealer->is_active) {
                throw ValidationException::withMessages(['action' => 'Đại lý đã thay đổi hoặc ngừng nhận đăng ký.']);
            }
            [$from, $to] = match ($action) {
                'approve' => [UserStatus::Pending, UserStatus::Active], 'reject' => [UserStatus::Pending, UserStatus::Rejected],
                'block' => [UserStatus::Active, UserStatus::Blocked], 'reactivate' => [UserStatus::Blocked, UserStatus::Active],
                default => throw ValidationException::withMessages(['action' => 'Thao tác không hợp lệ.']),
            };
            if ($target->status !== $from) {
                throw ValidationException::withMessages(['action' => 'Trạng thái đã thay đổi. Vui lòng tải lại trang.']);
            }
            $reason = $action === 'reject' ? ($data['rejection_reason'] ?? null) : null;
            $target->forceFill(['status' => $to, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'rejection_reason' => $reason])->save();
            $review = new ModerationReview;
            $review->forceFill(['sales_id' => $target->id, 'reviewed_by' => $actor->id, 'from_status' => $from->value, 'to_status' => $to->value,
                'rejection_reason' => $reason, 'reviewed_at' => $target->reviewed_at])->save();
        }, 3);
    }

    public function resubmit(User $account): void
    {
        DB::transaction(function () use ($account) {
            $dealer = Dealer::whereKey($account->dealer_id)->lockForUpdate()->firstOrFail();
            $account = User::whereKey($account->id)->lockForUpdate()->firstOrFail();
            if (! $dealer->is_active || $account->dealer_id !== $dealer->id || $account->status !== UserStatus::Rejected
                || ! in_array($account->role, [UserRole::Sales, UserRole::Dealer], true)) {
                throw ValidationException::withMessages(['action' => 'Chỉ hồ sơ bị từ chối tại đại lý đang hoạt động được gửi lại.']);
            }
            $account->forceFill(['status' => UserStatus::Pending, 'reviewed_by' => null, 'reviewed_at' => null, 'rejection_reason' => null])->save();
            $review = new ModerationReview;
            $review->forceFill(['sales_id' => $account->id, 'reviewed_by' => $account->id, 'from_status' => 'rejected', 'to_status' => 'pending', 'reviewed_at' => now()])->save();
        }, 3);
    }
}
