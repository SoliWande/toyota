<x-public.layout :title="$profile['name']" :canonical="route('sales.public.show', ['slug' => $profile['slug']])">
    <section class="public-container public-section">
        <div class="mx-auto max-w-2xl space-y-6">
            <section class="rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
                <div class="flex flex-col items-center gap-5 text-center sm:flex-row sm:text-left">
                    <x-sales-avatar sales-id="" :has-avatar="$profile['hasAvatar']" :src="route('sales.public.avatar', ['slug' => $profile['slug']])" class="h-28 w-28" />
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-widest text-red-700">Toyota Community</p>
                        <h1 class="mt-2 break-words text-3xl font-bold">{{ $profile['name'] }}</h1>
                        <p class="mt-3 break-words text-stone-600">Đại lý hiện tại: {{ $profile['dealerName'] ?? 'Chưa có thông tin đại lý' }}</p>
                        <p class="mt-2 text-sm text-stone-500">Điện thoại: {{ $profile['maskedPhone'] }}</p>
                        @if ($profile['dealerUrl'])<a class="mt-3 inline-flex py-2 text-sm font-semibold text-red-700 hover:underline" href="{{ $profile['dealerUrl'] }}">Xem đại lý hiện tại →</a>@endif
                    </div>
                </div>
            </section>
            <section class="rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
                <h2 class="text-xl font-semibold">Khách hàng đã được duyệt</h2>
                <p class="mt-4 text-5xl font-bold text-red-700">{{ number_format($profile['approvedTotal']) }}</p>
                <p class="mt-3 text-sm leading-6 text-stone-500">Tổng thành tích cá nhân trong toàn chiến dịch, được giữ nguyên khi chuyển đại lý.</p>
            </section>
            <section class="rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
                <h2 class="text-xl font-semibold">Thành tích 3 tháng gần đây</h2>
                <dl class="mt-5 grid gap-4 sm:grid-cols-3">
                    @foreach ($profile['months'] as $month)
                        <div class="rounded-xl bg-stone-50 p-5"><dt class="text-sm text-stone-600">Tháng {{ $month['label'] }}</dt><dd class="mt-2 text-3xl font-bold text-red-700">{{ number_format($month['count']) }}</dd></div>
                    @endforeach
                </dl>
                <p class="mt-4 text-xs leading-6 text-stone-500">Chỉ tính khách hàng đã được Admin duyệt, phân tháng theo thời điểm khai báo như quy tắc thống kê hiện tại.</p>
            </section>
            <section aria-labelledby="customer-privacy-heading" class="rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
                <h2 id="customer-privacy-heading" class="text-xl font-semibold">Khách hàng của Sales</h2>
                <p class="mt-3 text-sm leading-6 text-stone-600">Trang này chỉ hiển thị thống kê tổng hợp. Thông tin từng khách hàng được bảo vệ theo quyền riêng tư.</p>
            </section>
        </div>
    </section>
</x-public.layout>
