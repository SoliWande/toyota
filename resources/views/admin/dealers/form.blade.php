<x-layout :title="$dealer->exists ? 'Sửa đại lý' : 'Thêm đại lý'">
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('admin.dealers.index') }}" class="text-sm text-slate-500 hover:text-red-600">← Danh sách đại lý</a>
        <h1 class="mb-7 mt-4 text-3xl font-bold">{{ $dealer->exists ? 'Sửa đại lý' : 'Thêm đại lý' }}</h1>
        <x-admin-feedback />
        <form method="POST" action="{{ $dealer->exists ? route('admin.dealers.update', $dealer) : route('admin.dealers.store') }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
            @csrf
            @if ($dealer->exists) @method('PUT') @endif
            @foreach (['name' => ['Tên đại lý', 255, true], 'code' => ['Mã đại lý', 50, true], 'province' => ['Tỉnh/thành phố (tùy chọn)', 255, false], 'phone' => ['Điện thoại (tùy chọn)', 30, false]] as $field => [$label, $max, $required])
                <div>
                    <label for="{{ $field }}" class="text-sm font-medium">{{ $label }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'phone' ? 'tel' : 'text' }}" value="{{ old($field, $dealer->$field) }}" maxlength="{{ $max }}" @required($required)
                        class="form-input" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}">
                </div>
            @endforeach
            <div>
                <label for="address" class="text-sm font-medium">Địa chỉ (tùy chọn)</label>
                <textarea id="address" name="address" class="form-input" rows="3" maxlength="2000">{{ old('address', $dealer->address) }}</textarea>
            </div>
            <div>
                <label for="is_active" class="text-sm font-medium">Trạng thái</label>
                <select id="is_active" name="is_active" class="form-input" required>
                    <option value="1" @selected((bool) old('is_active', $dealer->is_active))>Active — nhận đăng ký Sales</option>
                    <option value="0" @selected(! (bool) old('is_active', $dealer->is_active))>Inactive — ngừng nhận đăng ký Sales</option>
                </select>
                <p class="mt-2 text-sm text-slate-500">Ngừng hoạt động sẽ giữ nguyên tài khoản Sales và lịch sử của đại lý.</p>
            </div>
            <div class="flex flex-wrap items-center gap-5">
                <button type="submit" class="button-primary">{{ $dealer->exists ? 'Lưu thay đổi' : 'Tạo đại lý' }}</button>
                <a href="{{ route('admin.dealers.index') }}" class="text-sm text-slate-600 hover:underline">Hủy</a>
            </div>
        </form>
    </div>
</x-layout>
