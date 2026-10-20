<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\CustomerSubmission;

class SubmissionIntegrity
{
    // Call with eager-loaded relationships. A Sales transfer does not invalidate its historic Dealer.
    public function warning(CustomerSubmission $submission): ?string
    {
        if ($submission->dealer === null) {
            return 'Thiếu đại lý lịch sử hợp lệ. Cần Admin kiểm tra; không thể duyệt.';
        }
        if ($submission->source === 'sales' && ($submission->sales === null || $submission->sales->role !== UserRole::Sales)) {
            return 'Quan hệ Sales không hợp lệ. Không thể duyệt khai báo này.';
        }
        if (($submission->source === 'dealer_qr' && $submission->sales_id !== null)
            || ! in_array($submission->source, ['sales', 'dealer_qr'], true)) {
            return 'Nguồn đăng ký hoặc quan hệ Sales không hợp lệ. Không thể duyệt.';
        }

        return null;
    }
}
