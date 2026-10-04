<?php

namespace App\Actions;

use App\Enums\SubmissionStatus;
use App\Models\CustomerSubmission;
use App\Models\ModerationReview;
use App\Models\User;
use App\Services\ApprovedSubmissionFinder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApproveCustomerSubmission
{
    public function __construct(private ApprovedSubmissionFinder $duplicates) {}

    public function execute(User $admin, CustomerSubmission $submission, ?string $note = null): void
    {
        Gate::forUser($admin)->authorize('review', $submission);

        try {
            DB::transaction(function () use ($admin, $submission, $note) {
                $locked = CustomerSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($admin)->authorize('review', $locked);

                if ($locked->status !== SubmissionStatus::Pending) {
                    throw ValidationException::withMessages(['submission' => 'Khai báo không còn chờ duyệt. Vui lòng kiểm tra lại trạng thái.']);
                }

                if ($this->duplicates->find($locked->facebook_url)) {
                    throw $this->duplicateError();
                }

                $time = now();
                $locked->forceFill([
                    'status' => SubmissionStatus::Approved,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => $time,
                    'admin_note' => $note,
                    'rejection_reason' => null,
                ])->save();

                $review = new ModerationReview;
                $review->forceFill([
                    'customer_submission_id' => $locked->id,
                    'reviewed_by' => $admin->id,
                    'from_status' => SubmissionStatus::Pending->value,
                    'to_status' => SubmissionStatus::Approved->value,
                    'admin_note' => $note,
                    'reviewed_at' => $time,
                ])->save();
            }, 3);
        } catch (QueryException $exception) {
            // The unique index is the final guard when two different records race.
            if (($exception->errorInfo[1] ?? null) === 1062
                && str_contains($exception->errorInfo[2] ?? '', 'submissions_approved_profile_unique')) {
                throw $this->duplicateError();
            }

            if (in_array($exception->errorInfo[1] ?? null, [1205, 1213], true)) {
                throw ValidationException::withMessages(['submission' => 'Có thao tác duyệt khác đang diễn ra. Vui lòng tải lại và thử lại.']);
            }

            throw $exception;
        }
    }

    private function duplicateError(): ValidationException
    {
        return ValidationException::withMessages([
            'duplicate' => 'Facebook profile này đã được ghi nhận ở một khai báo đã duyệt. Không thể duyệt lần nữa; khai báo này vẫn chờ duyệt. Vui lòng xem record trước đó.',
        ]);
    }
}
