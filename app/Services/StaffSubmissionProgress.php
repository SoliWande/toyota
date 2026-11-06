<?php

namespace App\Services;

use App\Models\CustomerSubmission;
use Illuminate\Support\Facades\DB;

/** Private authorized detail pages only. Never export internal audit JSON/reviewer notes. */
final class StaffSubmissionProgress
{
    public function data(CustomerSubmission $submission): array
    {
        $verification = app(SubmissionVerification::class);
        $state = $verification->state($submission->id);
        $progress = $verification->evidencedProgress($submission, $state);
        $history = [['label' => 'Đã khai báo hồ sơ', 'at' => $submission->submitted_at]];
        foreach ($submission->moderationReviews()->select(['to_status', 'reviewed_at'])->orderBy('id')->get() as $review) {
            $history[] = ['label' => match ($review->to_status) {
                'approved' => 'Admin đã duyệt hồ sơ', 'rejected' => 'Admin đã từ chối hồ sơ', default => 'Cập nhật trạng thái hồ sơ',
            }, 'at' => $review->reviewed_at];
        }
        $labels = ['group' => 'Cập nhật bằng chứng Group', 'supplement' => 'Yêu cầu bổ sung thông tin',
            'response' => 'Đã tiếp nhận thông tin bổ sung', 'close_supplement' => 'Đã đóng yêu cầu bổ sung', 'blocker' => 'Cập nhật tình trạng đối chiếu'];
        foreach (DB::table('submission_verification_audits')->where('submission_id', $submission->id)
            ->whereIn('action', array_keys($labels))->orderBy('id')->get(['action', 'created_at']) as $audit) {
            $history[] = ['label' => $labels[$audit->action], 'at' => $audit->created_at];
        }
        usort($history, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));

        return ['group' => match ($progress) {
            'not_requested' => 'Chưa ghi nhận yêu cầu vào Group', 'requested' => 'Đã xác nhận gửi yêu cầu vào Group',
            'member' => 'Đã xác nhận thành viên Group', default => 'Chưa có bằng chứng xác minh Group',
        }, 'supplement' => match ($state?->supplement_state) {
            'open' => 'Cần bổ sung thông tin', 'submitted' => 'Đã gửi bổ sung, chờ xác minh', default => null,
        }, 'blocker' => isset(SubmissionVerification::BLOCKERS[$state?->blocker_code ?? '']) ? SubmissionVerification::BLOCKERS[$state->blocker_code] : null,
            'history' => $history];
    }
}
