<?php

namespace App\Actions;

use App\Enums\SubmissionStatus;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\SubmissionEdit;
use App\Models\User;
use App\Services\AdministrativeDivisions;
use App\Services\RegistrationPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class UpdateCustomerSubmission
{
    public static function version(CustomerSubmission $submission): string
    {
        $snapshot = [];
        foreach (['id', 'status', 'source', 'dealer_id', 'sales_id', 'customer_name', 'phone', 'phone_normalized',
            'address_line', 'province_code', 'ward_code', 'vehicle_model', 'vehicle_description',
            'facebook_url', 'updated_at', 'reviewed_at'] as $field) {
            $snapshot[$field] = $submission->getRawOriginal($field);
        }

        return hash_hmac('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR), config('app.key'));
    }

    public function execute(User $admin, CustomerSubmission $submission, array $data): void
    {
        Gate::forUser($admin)->authorize('review', $submission);
        if (! Schema::hasTable('submission_edits')) {
            throw ValidationException::withMessages(['submission' => 'Chưa áp dụng migration nhật ký chỉnh sửa. Chưa thể lưu thay đổi.']);
        }
        $phone = isset($data['phone']) ? RegistrationPhone::normalize($data['phone']) : null;
        $lock = 'submission-phone-'.substr(RegistrationPhone::key(DB::connection()->getDatabaseName().'|'.$phone), 0, 40);
        if ((int) DB::selectOne('SELECT GET_LOCK(?, 5) AS acquired', [$lock])->acquired !== 1) {
            throw ValidationException::withMessages(['phone' => 'Có thao tác chỉnh sửa khác đang xử lý. Vui lòng thử lại.']);
        }
        try {
            DB::transaction(function () use ($admin, $submission, $data, $phone) {
                $locked = CustomerSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($admin)->authorize('review', $locked);
                if (! hash_equals(self::version($locked), $data['edit_version'])) {
                    throw ValidationException::withMessages(['submission' => 'Khai báo đã được thay đổi bởi thao tác khác. Vui lòng tải lại trước khi sửa.']);
                }
                $dealerId = array_key_exists('dealer_id', $data) ? ($data['dealer_id'] === null ? null : (int) $data['dealer_id']) : $locked->dealer_id;
                $salesId = array_key_exists('sales_id', $data) ? ($data['sales_id'] === null ? null : (int) $data['sales_id']) : $locked->sales_id;
                $reassign = $dealerId !== $locked->dealer_id || $salesId !== $locked->sales_id;
                if ($reassign && $locked->status !== SubmissionStatus::Pending) {
                    throw ValidationException::withMessages(['dealer_id' => 'Chỉ được sửa liên kết Dealer/Sales khi khai báo đang chờ duyệt. Thành tích lịch sử được giữ nguyên.']);
                }
                if ($locked->source === 'sales' && ! User::whereKey($salesId)->where('role', 'sales')->exists()) {
                    throw ValidationException::withMessages(['sales_id' => 'Quan hệ Sales không hợp lệ. Vui lòng chọn Sales hợp lệ cho khai báo chờ duyệt.']);
                }
                if ($reassign) {
                    if ($dealerId === null || ! Dealer::whereKey($dealerId)->exists()) {
                        throw ValidationException::withMessages(['dealer_id' => 'Vui lòng chọn đại lý hợp lệ.']);
                    }
                    if ($locked->source === 'dealer_qr' && $salesId !== null) {
                        throw ValidationException::withMessages(['sales_id' => 'Khách QR giữ nguyên nguồn và không gán Sales trong luồng chỉnh sửa này.']);
                    }
                    if ($locked->source === 'sales') {
                        // Same user lock as Sales transfers: the relationship cannot change during validation/save.
                        $sales = User::whereKey($salesId)->where('role', 'sales')->lockForUpdate()->first();
                        if (! $sales || $sales->dealer_id !== $dealerId) {
                            throw ValidationException::withMessages(['sales_id' => 'Sales phải thuộc đại lý được chọn.']);
                        }
                    }
                }
                if ($phone !== null && $phone !== RegistrationPhone::normalize($locked->phone ?? '')) {
                    $digits = substr($phone, 1);
                    $duplicate = CustomerSubmission::whereKeyNot($locked->id)->whereIn('status', ['pending', 'approved'])
                        ->where(fn ($query) => $query->where('phone_normalized', $phone)
                            ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '.', ''), '(', ''), ')', '') IN (?, ?, ?)", [$phone, '+84'.$digits, '0084'.$digits]))->exists();
                    if ($duplicate) {
                        throw ValidationException::withMessages(['phone' => 'Số điện thoại đang thuộc một khai báo chờ duyệt hoặc đã duyệt khác. Vui lòng kiểm tra trùng khách hàng.']);
                    }
                }
                $before = $locked->getAttributes();
                $locked->customer_name = $data['customer_name'];
                $locked->phone = $phone;
                $locked->phone_normalized = $phone;
                $locked->dealer_id = $dealerId;
                $locked->sales_id = $salesId;
                foreach (['vehicle_model', 'vehicle_description'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $locked->$field = $data[$field];
                    }
                }
                if (array_intersect(['province_code', 'ward_code', 'address_line'], array_keys($data))) {
                    $directory = app(AdministrativeDivisions::class);
                    $locked->province_code = $data['province_code'] ?? null;
                    $locked->ward_code = $data['ward_code'] ?? null;
                    $locked->address_line = $data['address_line'] ?? null;
                    $locked->province_name = $locked->province_code ? $directory->province($locked->province_code)['name'] : null;
                    $locked->ward_name = $locked->province_code && $locked->ward_code ? $directory->ward($locked->province_code, $locked->ward_code)['name'] : null;
                }
                $changes = [];
                foreach ($locked->getDirty() as $field => $value) {
                    $changes[$field] = ['before' => $this->auditValue($field, $before[$field] ?? null), 'after' => $this->auditValue($field, $value)];
                }
                if ($changes === []) {
                    return;
                }
                $locked->saveAdminRevision($admin);
                $edit = new SubmissionEdit;
                $edit->forceFill(['customer_submission_id' => $locked->id, 'submission_id_snapshot' => $locked->id, 'edited_by' => $admin->id,
                    'changes' => $changes, 'edited_at' => now()])->save();
            }, 3);
        } finally {
            DB::select('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    private function auditValue(string $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (in_array($field, ['dealer_id', 'sales_id', 'province_code', 'ward_code', 'vehicle_model'])) {
            return $value;
        }
        if (in_array($field, ['phone', 'phone_normalized'])) {
            return '*******'.mb_substr((string) $value, -3);
        }
        if ($field === 'customer_name') {
            return mb_substr((string) $value, 0, 1).'***';
        }

        // Do not duplicate addresses or free text in an audit/application log.
        return '[đã ẩn]';
    }
}
