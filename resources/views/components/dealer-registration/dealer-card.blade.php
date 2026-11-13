@props(['dealer', 'compact' => false])
@php($callPhone = app(\App\Services\DealerCallPhone::class)->forDealer($dealer))
@php($hasPhoto = ! $compact && app(\App\Services\DealerIntroductionContact::class)->photoFor($dealer) !== null)
<section class="dr-dealer-card {{ $compact ? 'dr-dealer-compact' : 'dr-referring-dealer' }}" aria-label="Đại lý giới thiệu">
    <span class="dr-dealer-symbol">
        @if ($compact)<x-dealer-registration.icon name="dealer" :size="22" />@else<img class="dr-icon" src="{{ asset('dealer-registration/referring-dealer-store.png') }}" width="28" height="28" alt="">@endif
        @if ($hasPhoto)<img class="dr-dealer-photo" src="{{ route('dealers.photo', $dealer->id) }}" alt="{{ $dealer->name }}" width="64" height="64" loading="lazy" data-fallback-image>@endif
    </span>
    <div class="dr-dealer-copy"><small>
        @if ($compact)
            ĐẠI LÝ GIỚI THIỆU
        @else
            <span class="dr-referring-desktop-label">ĐẠI LÝ GIỚI THIỆU</span><span class="dr-referring-mobile-label">Đăng ký qua đại lý</span>
        @endif
    </small><h2>{{ $dealer->name }}</h2>
        @if (! $compact)<div class="dr-dealer-meta">
            @if ($dealer->address)<span><img class="dr-icon" src="{{ asset('dealer-registration/referring-dealer-location.png') }}" width="16" height="16" alt="">{{ $dealer->address }}</span>@endif
            @if ($dealer->opening_hours)<span><img class="dr-icon" src="{{ asset('dealer-registration/referring-dealer-clock.png') }}" width="16" height="16" alt="">{{ $dealer->opening_hours }}</span>@endif
        </div>@endif
    </div>
    @if ($callPhone)<a class="dr-button dr-button-muted dr-dealer-call" href="tel:{{ $callPhone }}" aria-label="Gọi đại lý">@if ($compact)<x-dealer-registration.icon name="phone" :size="18" />@else<img class="dr-icon" src="{{ asset('dealer-registration/referring-dealer-phone.png') }}" width="18" height="18" alt="">@endif<span>Gọi đại lý</span></a>@endif
</section>
