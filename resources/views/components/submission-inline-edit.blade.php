@props(['submission', 'dealers', 'salesOptions', 'filters'])
@inject('directory', 'App\Services\AdministrativeDivisions')
@php
    $restore = is_scalar(old('edit_submission_id')) && (string) old('edit_submission_id') === (string) $submission->id;
    $value = function ($field) use ($restore, $submission) {
        $raw = $restore ? old($field, $submission->$field) : $submission->$field;
        return is_scalar($raw) ? $raw : null;
    };
    $province = $value('province_code');
    $wards = is_string($province) ? $directory->wards($province) : [];
    $pending = $submission->status === \App\Enums\SubmissionStatus::Pending;
    $qr = $submission->source === 'dealer_qr';
@endphp
<details class="mt-4 border-t border-slate-100 pt-3" data-inline-editor @if ($restore) open @endif>
    <summary class="min-h-12 cursor-pointer py-3 text-sm font-semibold text-red-700">Chỉnh sửa</summary>
    <form method="POST" action="{{ route('admin.submissions.update', ['submission' => $submission, ...$filters]) }}" data-prevent-double-submit class="space-y-5 rounded-xl bg-slate-50 p-4">
        @csrf
        @method('PATCH')
        <input type="hidden" name="edit_submission_id" value="{{ $submission->id }}">
        <input type="hidden" name="edit_version" value="{{ $restore && is_string(old('edit_version')) ? old('edit_version') : \App\Actions\UpdateCustomerSubmission::version($submission) }}">
        @if ($restore && $errors->any())
            <div role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-800"><p class="font-semibold">Chưa lưu được thay đổi.</p><ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="edit-name-{{ $submission->id }}" class="text-sm font-medium">Họ và tên</label><input id="edit-name-{{ $submission->id }}" name="customer_name" value="{{ $value('customer_name') }}" required maxlength="255" class="form-input"></div>
            <div><label for="edit-phone-{{ $submission->id }}" class="text-sm font-medium">Số điện thoại</label><input id="edit-phone-{{ $submission->id }}" name="phone" value="{{ $value('phone') }}" type="tel" maxlength="20" @required($qr) class="form-input"></div>
        </div>
        <fieldset data-customer-address data-wards-url="{{ route('admin.submissions.address.wards', ['province' => '__PROVINCE__']) }}" class="space-y-4">
            <legend class="text-sm font-semibold">Địa chỉ</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="edit-province-{{ $submission->id }}" class="text-sm font-medium">Tỉnh/Thành</label><select id="edit-province-{{ $submission->id }}" name="province_code" class="form-input" @required($qr)><option value="">Chọn Tỉnh/Thành</option>@foreach ($directory->provinces() as $item)<option value="{{ $item['code'] }}" @selected($province === $item['code'])>{{ $item['name'] }}</option>@endforeach</select></div>
                <div><label for="edit-ward-{{ $submission->id }}" class="text-sm font-medium">Phường/Xã</label><select id="edit-ward-{{ $submission->id }}" name="ward_code" class="form-input" @required($qr) @disabled($wards === [])><option value="">Chọn Phường/Xã</option>@foreach ($wards as $item)<option value="{{ $item['code'] }}" @selected($value('ward_code') === $item['code'])>{{ $item['name'] }}</option>@endforeach</select></div>
            </div>
            <p data-address-status role="status" class="text-xs text-slate-500"></p><button type="button" data-address-retry hidden class="text-sm text-red-700">Thử lại danh sách Phường/Xã</button>
            <div><label for="edit-address-{{ $submission->id }}" class="text-sm font-medium">Địa chỉ chi tiết</label><input id="edit-address-{{ $submission->id }}" name="address_line" value="{{ $value('address_line') }}" maxlength="500" @required($qr) class="form-input"></div>
        </fieldset>
        @if ($qr)
            <div><label for="edit-vehicle-{{ $submission->id }}" class="text-sm font-medium">Xe đang sử dụng</label><input id="edit-vehicle-{{ $submission->id }}" name="vehicle_description" value="{{ $value('vehicle_description') }}" maxlength="255" required class="form-input"></div>
        @else
            <div><label for="edit-vehicle-{{ $submission->id }}" class="text-sm font-medium">Xe đang sử dụng</label><select id="edit-vehicle-{{ $submission->id }}" name="vehicle_model" required class="form-input">@foreach (config('vehicles.toyota_models') as $code => $name)<option value="{{ $code }}" @selected($value('vehicle_model') === $code)>{{ $name }}</option>@endforeach</select></div>
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="edit-dealer-{{ $submission->id }}" class="text-sm font-medium">Đại lý nhận khai báo</label><select id="edit-dealer-{{ $submission->id }}" name="dealer_id" class="form-input" @disabled(! $pending)><option value="">Chưa xác minh đại lý</option>@foreach ($dealers as $dealer)<option value="{{ $dealer->id }}" @selected((string) $value('dealer_id') === (string) $dealer->id)>{{ $dealer->name }}</option>@endforeach</select></div>
            <div><label for="edit-sales-{{ $submission->id }}" class="text-sm font-medium">Sales liên kết</label><select id="edit-sales-{{ $submission->id }}" name="sales_id" class="form-input" @disabled(! $pending || $qr)><option value="">{{ $qr ? 'Khách tự đăng ký' : 'Chọn Sales' }}</option>@if (! $qr)@foreach ($salesOptions as $sales)<option value="{{ $sales->id }}" data-dealer-id="{{ $sales->dealer_id }}" @selected((string) $value('sales_id') === (string) $sales->id)>{{ $sales->name }}</option>@endforeach @endif</select></div>
        </div>
        <p class="text-xs leading-5 text-slate-500">Chỉ bản ghi chờ duyệt được sửa liên kết hợp lệ. Khách QR không gán Sales. Khai báo đã xử lý giữ nguyên Dealer/Sales lịch sử; không đổi trạng thái khi lưu.</p>
        <div class="flex flex-wrap gap-3"><button type="submit" class="button-primary">Lưu thay đổi</button><button type="button" data-cancel-inline class="rounded-xl border border-slate-300 px-5 py-3 font-semibold">Hủy</button></div>
        <p data-submitting-status role="status" aria-live="polite" class="text-sm text-slate-600"></p>
    </form>
</details>
