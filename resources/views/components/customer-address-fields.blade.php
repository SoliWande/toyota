@props(['submission', 'required' => false, 'wardsUrl' => null, 'restoreInput' => true])
@inject('addressDirectory', 'App\Services\AdministrativeDivisions')
@php
    $provinceCode = $restoreInput ? old('province_code', $submission->province_code) : $submission->province_code;
    $wardCode = $restoreInput ? old('ward_code', $submission->ward_code) : $submission->ward_code;
    $addressLine = $restoreInput ? old('address_line', $submission->address_line) : $submission->address_line;
    $addressLine = is_string($addressLine) ? $addressLine : '';
    $wards = is_string($provinceCode) ? $addressDirectory->wards($provinceCode) : [];
@endphp
<fieldset data-customer-address data-wards-url="{{ $wardsUrl ?? route('sales.address.wards', ['province' => '__PROVINCE__']) }}" class="space-y-5 border-t border-slate-100 pt-5">
    <legend class="px-1 text-base font-semibold">{{ $required ? 'Địa chỉ khách hàng' : 'Địa chỉ khách hàng (tùy chọn)' }}</legend>
    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="province_code" class="text-sm font-medium">Tỉnh/Thành</label>
            <select id="province_code" name="province_code" @required($required) autocomplete="address-level1" class="form-input" aria-invalid="{{ $errors->has('province_code') ? 'true' : 'false' }}" @if ($errors->has('province_code')) aria-describedby="province_code-error" @endif>
                <option value="">Chọn Tỉnh/Thành</option>
                @foreach ($addressDirectory->provinces() as $province)
                    <option value="{{ $province['code'] }}" @selected($provinceCode === $province['code'])>{{ $province['name'] }}</option>
                @endforeach
            </select>
            @error('province_code')<p id="province_code-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="ward_code" class="text-sm font-medium">Phường/Xã</label>
            <select id="ward_code" name="ward_code" @required($required) autocomplete="address-level2" class="form-input" @disabled($wards === []) aria-invalid="{{ $errors->has('ward_code') ? 'true' : 'false' }}" aria-describedby="address-status{{ $errors->has('ward_code') ? ' ward_code-error' : '' }}">
                <option value="">{{ $wards === [] ? 'Chọn Tỉnh/Thành trước' : 'Chọn Phường/Xã' }}</option>
                @foreach ($wards as $ward)
                    <option value="{{ $ward['code'] }}" @selected($wardCode === $ward['code'])>{{ $ward['name'] }}</option>
                @endforeach
            </select>
            @error('ward_code')<p id="ward_code-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
    </div>
    <div>
        <p id="address-status" data-address-status role="status" aria-live="polite" class="text-xs text-slate-500"></p>
        <button data-address-retry type="button" hidden class="mt-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium">Thử lại</button>
    </div>
    <div>
        <label for="address_line" class="text-sm font-medium">Địa chỉ chi tiết</label>
        <input id="address_line" name="address_line" @required($required) type="text" autocomplete="street-address" maxlength="500" value="{{ $addressLine }}" placeholder="Số nhà, tên đường hoặc thôn/ấp" class="form-input" aria-invalid="{{ $errors->has('address_line') ? 'true' : 'false' }}" @if ($errors->has('address_line')) aria-describedby="address_line-error" @endif>
        @error('address_line')<p id="address_line-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        <p class="mt-2 text-xs leading-5 text-slate-500">Khi bổ sung địa chỉ, vui lòng chọn cả Tỉnh/Thành và Phường/Xã. Thông tin này không hiển thị công khai.</p>
    </div>
</fieldset>
