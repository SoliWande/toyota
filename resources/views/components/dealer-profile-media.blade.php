@props(['dealer'])
<section class="grid gap-6 rounded-2xl border border-stone-200 bg-white p-5 sm:p-7 lg:grid-cols-[1fr_240px]">
    <div class="relative flex min-h-48 items-center justify-center overflow-hidden rounded-xl bg-stone-100 text-stone-400">
        <div class="p-7 text-center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mx-auto h-16 w-16" aria-hidden="true"><path d="M3 21V7l9-4 9 4v14M3 11h18M8 21v-6h8v6M1 21h22" /></svg><p class="mt-3 text-sm">Ảnh đại lý đang được cập nhật</p></div>
        @if ($dealer->photo_path)
            <img src="{{ route('dealers.photo', $dealer) }}" alt="{{ $dealer->name }}" class="absolute inset-0 h-full w-full object-cover" data-fallback-image loading="lazy">
        @endif
    </div>
    <div class="min-w-0 text-center">
        <h2 class="text-lg font-semibold">QR hồ sơ đại lý</h2>
        <img src="{{ route('dealers.qr', $dealer) }}" alt="Mã QR mở hồ sơ {{ $dealer->name }}" width="200" height="200" class="mx-auto mt-3 h-48 w-48 max-w-full" loading="lazy">
        <a href="{{ route('dealers.qr', ['dealer' => $dealer, 'download' => 1]) }}" download="dealer-{{ $dealer->id }}-qr.png" class="mt-3 inline-flex min-h-11 items-center justify-center rounded-lg bg-red-700 px-5 py-3 text-sm font-semibold text-white hover:bg-red-800">Tải mã QR (PNG)</a>
        <a href="{{ app(\App\Services\DealerProfile::class)->url($dealer) }}" class="mt-3 block break-all text-xs leading-5 text-stone-500 hover:text-red-700 hover:underline">{{ app(\App\Services\DealerProfile::class)->url($dealer) }}</a>
    </div>
</section>
