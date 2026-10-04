<?php

namespace App\Actions;

use App\Enums\SubmissionStatus;
use App\Models\CustomerSubmission;
use App\Models\ModerationReview;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RejectCustomerSubmission
{
    public function execute(User $admin, CustomerSubmission $submission, ?string $reason = null, ?string $note = null): void
    {
        Gate::forUser($admin)->authorize('review', $submission);

        try {
            DB::transaction(function () use ($admin, $submission, $reason, $note) {
                $locked = CustomerSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($admin)->authorize('review', $locked);
                if ($locked->status !== SubmissionStatus::Pending) {
                    throw ValidationException::withMessages(['submission' => 'Khai báo không còn chờ duyệt. Vui lòng kiểm tra lại trạng thái.']);
                }

                $time = now();
                $locked->forceFill([
                    'status' => SubmissionStatus::Rejected,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => $time,
                    'rejection_reason' => $reason,
                    'admin_note' => $note,
                ])->save();

                $review = new ModerationReview;
                $review->forceFill([
                    'customer_submission_id' => $locked->id,
                    'reviewed_by' => $admin->id,
                    'from_status' => SubmissionStatus::Pending->value,
                    'to_status' => SubmissionStatus::Rejected->value,
                    'rejection_reason' => $reason,
                    'admin_note' => $note,
                    'reviewed_at' => $time,
                ])->save();
            }, 3);
        } catch (QueryException $exception) {
            if (in_array($exception->errorInfo[1] ?? null, [1205, 1213], true)) {
                throw ValidationException::withMessages(['submission' => 'Có thao tác duyệt khác đang diễn ra. Vui lòng tải lại và thử lại.']);
            }
            throw $exception;
        }
    }
}
