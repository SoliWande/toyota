@props(['dealer', 'standing'])
<section class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-7">
    <h2 class="text-xl font-semibold">Thông tin đại lý</h2>
    <dl class="mt-5 grid gap-5 text-sm sm:grid-cols-2">
        @foreach (['code' => 'Mã đại lý', 'province' => 'Tỉnh / Thành phố', 'address' => 'Địa chỉ', 'phone' => 'Điện thoại', 'opening_hours' => 'Giờ mở cửa', 'website' => 'Website', 'facebook_url' => 'Facebook đại lý', 'zalo_url' => 'Zalo đại lý'] as $field => $label)
            <div class="min-w-0"><dt class="text-stone-500">{{ $label }}</dt><dd class="mt-1 whitespace-pre-line break-words">{{ $dealer->$field ?: 'Chưa cập nhật' }}</dd></div>
        @endforeach
        <div><dt class="text-stone-500">Trạng thái</dt><dd class="mt-1">{{ $dealer->is_active ? 'Đang hoạt động' : 'Ngừng hoạt động' }}</dd></div>
    </dl>
    <dl class="mt-7 grid grid-cols-2 gap-4 border-t border-stone-100 pt-6">
        <div><dt class="text-sm text-stone-500">Tổng điểm hợp lệ</dt><dd class="mt-2 text-3xl font-bold text-red-700">{{ number_format($standing?->score ?? 0) }}</dd></div>
        <div><dt class="text-sm text-stone-500">Thứ hạng toàn thời gian</dt><dd class="mt-2 text-3xl font-bold">{{ $standing?->rank ? '#'.$standing->rank : '—' }}</dd></div>
    </dl>
    <p class="mt-5 text-xs leading-6 text-stone-500">Chỉ tính khách hàng đã được Admin duyệt. Thành tích được giữ cho đại lý tại thời điểm khai báo.</p>
</section>
