<x-public.layout :title="$dealer->name" :canonical="app(\App\Services\DealerProfile::class)->url($dealer)">
    <section class="public-container public-section">
        <a href="{{ route('dealers.index') }}" class="text-sm text-stone-600 hover:text-red-700">← Đại lý đang hoạt động</a>
        <h1 class="public-heading mb-7 mt-5 break-words">{{ $dealer->name }}</h1>
        @if ($dealer->is_active)<a href="#registration" class="button-primary mb-6 inline-flex">Đăng ký tham gia</a>@endif
        <x-dealer-profile-media :dealer="$dealer" />
        <div class="mt-6"><x-dealer-details :dealer="$dealer" :standing="$standing" /></div>
        <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-5 sm:p-7">
            <h2 class="text-xl font-semibold">Khách hàng đã được duyệt</h2>
            <p class="mt-3 text-3xl font-bold text-red-700">{{ number_format($standing?->score ?? 0) }}</p>
            <p class="mt-2 text-sm leading-6 text-stone-500">Khách hàng hợp lệ được ghi nhận cho đại lý tại thời điểm khai báo.</p>
        </section>
        <a href="{{ route('leaderboard', ['period' => 'all']) }}" class="mt-6 inline-flex py-3 text-sm font-semibold text-red-700 hover:underline">Xem bảng xếp hạng đại lý →</a>
        @if ($dealer->is_active)<x-dealer-registration-form :dealer="$dealer" />@endif
    </section>
</x-public.layout>
