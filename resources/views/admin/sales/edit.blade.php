<x-layout title="Chỉnh sửa Sales">
    <div class="mx-auto max-w-xl">
        <a class="text-sm text-slate-500" href="{{ route('admin.sales.show', $sales) }}">← Chi tiết Sales</a>
        <h1 class="mt-4 text-3xl font-bold">Chỉnh sửa Sales</h1>
        <x-admin-feedback />
        <form method="POST" action="{{ route('admin.sales.update', $sales) }}" class="mt-6 space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
            @csrf @method('PATCH')
            <p class="break-all text-sm">Email cố định: {{ $sales->email }}</p>
            <label for="name" class="block text-sm font-medium">Họ tên</label>
            <input id="name" name="name" class="form-input" value="{{ old('name', $sales->name) }}" maxlength="255" required>
            <label for="phone" class="block text-sm font-medium">Điện thoại</label>
            <input id="phone" name="phone" type="tel" class="form-input" value="{{ old('phone', $sales->phone) }}" maxlength="30">
            <label for="dealer_id" class="block text-sm font-medium">Đại lý hiện tại</label>
            <select id="dealer_id" name="dealer_id" class="form-input" required>
                @foreach ($dealers as $dealer)
                    <option value="{{ $dealer->id }}" @selected((string) old('dealer_id', $sales->dealer_id) === (string) $dealer->id)>{{ $dealer->name }}{{ $dealer->is_active ? '' : ' (Ngừng hoạt động)' }}</option>
                @endforeach
            </select>
            <p class="text-sm leading-6 text-slate-600">Chuyển đại lý chỉ áp dụng cho submissions mới. Thành tích trước đây giữ nguyên đại lý đã lưu lúc tạo.</p>
            <button type="submit" class="button-primary w-full">Lưu thay đổi</button>
        </form>
    </div>
</x-layout>
