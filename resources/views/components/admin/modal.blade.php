@props(['id', 'title', 'subtitle' => null, 'variant' => 'wide'])
<dialog id="{{ $id }}" {{ $attributes->class(['ad-modal', 'ad-modal-compact' => $variant === 'compact']) }} data-admin-modal aria-labelledby="{{ $id }}-title" @if ($subtitle) aria-describedby="{{ $id }}-subtitle" @endif>
    <header class="ad-modal-head"><div><h2 id="{{ $id }}-title">{{ $title }}</h2>@if ($subtitle)<p id="{{ $id }}-subtitle">{{ $subtitle }}</p>@endif</div><button type="button" class="ad-modal-close" data-admin-modal-close aria-label="Đóng"><x-admin.icon name="close" :size="18" /></button></header>
    <div class="ad-modal-body">{{ $slot }}</div>
    @isset($footer)<footer class="ad-modal-footer">{{ $footer }}</footer>@endisset
</dialog>
