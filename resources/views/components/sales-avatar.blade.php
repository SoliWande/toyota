@props(['salesId', 'hasAvatar' => false, 'src' => null])
<span {{ $attributes->class(['relative inline-flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100 text-slate-400']) }}>
    <svg viewBox="0 0 24 24" fill="currentColor" class="h-7 w-7" role="img" aria-label="Ảnh đại diện mặc định"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 8a8 8 0 0 1 16 0H4Z" /></svg>
    @if ($hasAvatar)
        <img src="{{ $src ?? route('sales.avatar', $salesId) }}" alt="Ảnh đại diện Sales" width="96" height="96" class="absolute inset-0 h-full w-full object-cover" data-sales-avatar loading="lazy">
    @endif
</span>
