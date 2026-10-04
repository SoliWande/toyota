<x-layout :title="$submission->exists ? 'Sửa khai báo' : 'Thêm khách hàng'">
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('sales.submissions.index') }}" class="text-sm text-slate-500 hover:text-red-600">← Khai báo của tôi</a>
        <h1 class="mb-3 mt-4 text-3xl font-bold">{{ $submission->exists ? 'Sửa khai báo' : 'Thêm khách hàng' }}</h1>
        <p class="mb-7 text-sm leading-6 text-slate-600">Khai báo thành viên bạn đã mời tham gia cộng đồng Facebook Toyota Veloz & Hilux. Thông tin xe Toyota và 1 ảnh bằng chứng là bắt buộc.</p>
        <x-admin-feedback />
        <form method="POST" enctype="multipart/form-data" action="{{ $submission->exists ? route('sales.submissions.update', $submission) : route('sales.submissions.store') }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-8">
            @csrf
            @if ($submission->exists) @method('PUT') @endif
            @foreach (['customer_name' => ['Họ tên khách hàng', 'text', 255, true, $submission->customer_name], 'facebook_url' => ['URL trang cá nhân Facebook', 'url', 2048, true, $submission->facebook_url], 'customer_phone' => ['Số điện thoại (tùy chọn)', 'tel', 30, false, $submission->phone]] as $field => [$label, $type, $max, $required, $value])
                <div>
                    <label for="{{ $field }}" class="text-sm font-medium">{{ $label }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field, $value) }}" maxlength="{{ $max }}" @required($required)
                        class="form-input" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" @if ($errors->has($field)) aria-describedby="{{ $field }}-error" @endif>
                    @error($field)<p id="{{ $field }}-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <fieldset class="space-y-5 border-t border-slate-100 pt-5">
                <legend class="px-1 text-base font-semibold">Thông tin xe Toyota (bắt buộc)</legend>
                <div>
                    <label for="vehicle_model" class="text-sm font-medium">Dòng xe Toyota</label>
                    <select id="vehicle_model" name="vehicle_model" required class="form-input" aria-invalid="{{ $errors->has('vehicle_model') ? 'true' : 'false' }}" @if ($errors->has('vehicle_model')) aria-describedby="vehicle_model-error" @endif>
                        <option value="">Chọn dòng xe Toyota</option>
                        @foreach (config('vehicles.toyota_models') as $value => $label)
                            <option value="{{ $value }}" @selected((string) old('vehicle_model', $submission->vehicle_model) === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('vehicle_model')<p id="vehicle_model-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach ([
                        'first_registration_year' => ['Năm đăng ký lần đầu', 'number', 4, '2022'],
                        'vehicle_color' => ['Màu xe', 'text', 50, 'Trắng'],
                        'license_plate' => ['Biển số xe', 'text', 20, '29D-428.12'],
                    ] as $field => [$label, $type, $max, $placeholder])
                        <div>
                            <label for="{{ $field }}" class="text-sm font-medium">{{ $label }}</label>
                            <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field, $submission->$field) }}" placeholder="{{ $placeholder }}" required maxlength="{{ $max }}"
                                @if ($field === 'first_registration_year') min="1900" max="{{ now(config('app.timezone'))->year }}" step="1" inputmode="numeric" @endif
                                class="form-input" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" @if ($errors->has($field)) aria-describedby="{{ $field }}-error" @endif>
                            @error($field)<p id="{{ $field }}-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
                <div>
                    <label for="evidence_image" class="text-sm font-medium">Ảnh bằng chứng (1 ảnh)</label>
                    <input id="evidence_image" name="evidence_image" type="file" accept="image/jpeg,image/png,image/webp" @required(!$submission->evidence_image_path) class="form-input"
                        aria-invalid="{{ $errors->has('evidence_image') ? 'true' : 'false' }}" aria-describedby="evidence-help{{ $errors->has('evidence_image') ? ' evidence_image-error' : '' }}">
                    <p id="evidence-help" class="mt-2 text-xs leading-5 text-slate-500">JPG, PNG hoặc WebP, tối đa 5 MB. Ảnh chỉ hiển thị cho bạn và admin. {{ $submission->evidence_image_path ? 'Để trống để giữ ảnh hiện tại; chọn ảnh mới để thay thế.' : 'Vui lòng chọn ảnh bằng chứng trước khi gửi.' }}</p>
                    @error('evidence_image')<p id="evidence_image-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                    @if ($submission->evidence_image_path)
                        <img src="{{ route('submissions.evidence', $submission) }}" alt="Ảnh bằng chứng hiện tại" class="mt-3 max-h-48 w-full rounded-xl border border-slate-200 object-contain">
                    @endif
                </div>
            </fieldset>
            <div>
                <label for="note" class="text-sm font-medium">Ghi chú (tùy chọn)</label>
                <textarea id="note" name="note" rows="4" maxlength="2000" class="form-input" aria-invalid="{{ $errors->has('note') ? 'true' : 'false' }}">{{ old('note', $submission->notes) }}</textarea>
                @error('note')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <p class="text-sm text-slate-500">Bạn chỉ có thể sửa hoặc xóa khai báo khi đang chờ duyệt.</p>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <button type="submit" class="button-primary">{{ $submission->exists ? 'Lưu thay đổi' : 'Gửi khai báo' }}</button>
                <a href="{{ route('sales.submissions.index') }}" class="text-center text-sm text-slate-600 hover:underline">Hủy</a>
            </div>
        </form>
    </div>
</x-layout>
