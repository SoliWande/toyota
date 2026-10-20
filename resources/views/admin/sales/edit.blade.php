<x-layout title="Chỉnh sửa Sales">
    <div class="mx-auto max-w-xl">
        <a class="text-sm text-slate-500" href="{{ route('admin.sales.show', $sales) }}">← Chi tiết Sales</a>
        <h1 class="mt-4 text-3xl font-bold">Chỉnh sửa Sales</h1>
        <x-admin-feedback />
        <form method="POST" action="{{ route('admin.sales.update', $sales) }}" enctype="multipart/form-data" class="mt-6 space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
            @csrf @method('PATCH')
            <p class="break-all text-sm">Email cố định: {{ $sales->email }}</p>
            <label for="name" class="block text-sm font-medium">Họ tên</label>
            <input id="name" name="name" class="form-input" value="{{ old('name', $sales->name) }}" maxlength="255" required>
            <label for="phone" class="block text-sm font-medium">Điện thoại</label>
            <input id="phone" name="phone" type="tel" class="form-input" value="{{ old('phone', $sales->phone) }}" maxlength="30">
            <div class="flex flex-wrap items-center gap-4"><x-sales-avatar :sales-id="$sales->id" :has-avatar="(bool) $sales->avatar_path" /><div class="min-w-0 flex-1"><label for="avatar" class="block text-sm font-medium">Avatar Sales</label><input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm"><p class="mt-2 text-xs text-slate-500">JPG, PNG hoặc WebP, tối đa 5 MB và 4096 × 4096 px. Avatar hiển thị trên profile public khi Sales đang hoạt động.</p></div></div>
            @if ($sales->public_slug)<a href="{{ route('sales.public.show', ['slug' => $sales->public_slug]) }}" class="inline-flex py-2 text-red-700 hover:underline" target="_blank" rel="noopener noreferrer">Xem profile public →</a>@endif
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
