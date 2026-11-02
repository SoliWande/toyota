@props(['dealer', 'compact' => false])
<section class="dr-dealer-card {{ $compact ? 'dr-dealer-compact' : '' }}" aria-label="Đại lý giới thiệu">
    @if (! $compact && $dealer->exists && $dealer->photo_path)
        <img class="dr-dealer-photo" src="{{ route('dealers.photo', $dealer->id) }}" alt="{{ $dealer->name }}" width="64" height="64" loading="lazy" data-fallback-image>
    @else
        <span class="dr-dealer-symbol"><x-dealer-registration.icon name="dealer" :size="22" /></span>
    @endif
    <div class="dr-dealer-copy"><small>ĐẠI LÝ GIỚI THIỆU</small><h2>{{ $dealer->name }}</h2>
        @if (! $compact)<div class="dr-dealer-meta">
            @if ($dealer->address)<span><x-dealer-registration.icon name="location" :size="16" />{{ $dealer->address }}</span>@endif
            @if ($dealer->opening_hours)<span><x-dealer-registration.icon name="clock" :size="16" />{{ $dealer->opening_hours }}</span>@endif
        </div>@endif
    </div>
    @if ($dealer->phone)<a class="dr-button dr-button-muted" href="tel:{{ preg_replace('/[^+0-9]/', '', $dealer->phone) }}"><x-dealer-registration.icon name="phone" :size="18" /><span>Gọi đại lý</span></a>@endif
</section>
