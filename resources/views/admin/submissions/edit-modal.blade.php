@php($qr = $selected->source === 'dealer_qr')
<x-admin.modal id="cq-edit" title="Sửa thông tin khách" :subtitle="$selected->customer_name.' · mọi thay đổi được ghi vào lịch sử'" class="cq-edit-modal">
    <form id="cq-edit-form" method="POST" action="{{ route('admin.submissions.update', ['submission' => $selected, ...$filters, 'selected' => $selected->id, 'c02' => 1]) }}" data-cq-action>
        @csrf @method('PATCH')
        <input type="hidden" name="edit_submission_id" value="{{ $selected->id }}">
        <input type="hidden" name="edit_version" value="{{ \App\Actions\UpdateCustomerSubmission::version($selected) }}">
        @if ($qr)
            @foreach (['address_line', 'province_code', 'ward_code'] as $field)<input type="hidden" name="{{ $field }}" value="{{ $selected->$field }}">@endforeach
        @endif
        @if (!$qr || $selected->status->value !== 'pending')<input type="hidden" name="dealer_id" value="{{ $selected->dealer_id }}">@endif
        <div class="cq-edit-fields">
            <label>Họ và tên<input name="customer_name" value="{{ $selected->customer_name }}" required maxlength="255"><small data-cq-field-error="customer_name" hidden></small></label>
            <label>Điện thoại<input type="tel" name="phone" value="{{ $selected->phone }}" @required($qr) maxlength="30"><small data-cq-field-error="phone" hidden></small></label>
            <label>Dòng xe
                @if ($qr)<input name="vehicle_description" value="{{ $selected->vehicle_description }}" required maxlength="255">
                @else<select name="vehicle_model" required>@if (!array_key_exists($selected->vehicle_model, config('vehicles.toyota_models')))<option value="{{ $selected->vehicle_model }}">{{ $selected->vehicle_model }}</option>@endif @foreach (config('vehicles.toyota_models') as $key => $label)<option value="{{ $key }}" @selected($selected->vehicle_model === $key)>Toyota {{ $label }}</option>@endforeach</select>@endif
                <small data-cq-field-error="{{ $qr ? 'vehicle_description' : 'vehicle_model' }}" hidden></small>
            </label>
            <label>Năm đăng ký<select disabled><option>{{ $selected->first_registration_year }}</option></select></label>
            <label>Biển số<input name="license_plate" value="{{ $selected->license_plate }}" @required(filled($selected->license_plate)) maxlength="20"><small data-cq-field-error="license_plate" hidden></small></label>
            <label>Tỉnh / Thành phố<select disabled><option>{{ $selected->province_name }}</option></select></label>
            <label>Facebook<input disabled value="{{ \Illuminate\Support\Str::limit(preg_replace('~^https?://(?:www\.)?~', '', $selected->facebook_url_normalized ?? ''), 38) }}"></label>
            <label>Đại lý ghi nhận<select @disabled(!$qr || $selected->status->value !== 'pending') @if ($qr && $selected->status->value === 'pending') name="dealer_id" @endif>@if (!$selected->dealer)<option value="" selected></option>@endif @foreach ($dealers as $dealer)<option value="{{ $dealer->id }}" @selected($dealer->id === $selected->dealer_id)>{{ $dealer->name }} · {{ $dealer->code }}</option>@endforeach</select><small data-cq-field-error="dealer_id" hidden></small></label>
        </div>
        <p class="cq-error" data-cq-form-error role="alert" hidden></p>
    </form>
    <x-slot:footer><x-admin.button variant="secondary" size="44" data-cq-modal-cancel>Huỷ</x-admin.button><x-admin.button type="submit" size="44" form="cq-edit-form">Lưu thay đổi</x-admin.button></x-slot:footer>
</x-admin.modal>
