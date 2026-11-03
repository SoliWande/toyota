@props(['dealer' => null, 'title' => 'Cộng đồng Toyota', 'landing' => false, 'preview' => false, 'success' => false, 'lookup' => false, 'rules' => false, 'description' => 'Kết nối cộng đồng chủ xe Toyota. Đăng ký cùng đại lý và chờ ban quản trị xác minh.'])
@php($preview = $preview && app()->environment(['local', 'testing']))
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="{{ $description }}">
    @if ($preview || $rules)<meta name="robots" content="noindex, nofollow">@endif
    @if ($rules)<link rel="canonical" href="{{ route('campaign.rules') }}">@elseif (! $preview && $dealer?->exists)<link rel="canonical" href="{{ app(\App\Services\DealerProfile::class)->url($dealer) }}">@endif
    <title>{{ $title }} · Toyota Community</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;family=Montserrat:wght@600;700&amp;display=swap">
    @if ($lookup || $rules)<link rel="stylesheet" href="{{ asset('dealer-registration/lookup-fonts.css') }}">@endif
    @vite($rules ? ['resources/css/dealer-registration.css'] : ['resources/css/dealer-registration.css', 'resources/js/dealer-registration.js'])
</head>
<body class="dr-site {{ $landing ? 'dr-landing' : 'dr-inner' }} {{ $success ? 'dr-success' : '' }} {{ $lookup ? 'dr-lookup-page' : '' }} {{ $rules ? 'dr-rules-page' : '' }}" data-dealer-registration @if ($preview) data-preview @endif>
    <a href="#dr-main" class="dr-skip">Bỏ qua điều hướng</a>
    @if ($preview)
        <aside class="dr-preview" aria-label="Chế độ xem trước">Xem trước giao diện · Không lưu thông tin
            <nav><a href="{{ route('dealer-registration.preview', 'landing') }}">D01</a><a href="{{ route('dealer-registration.preview', 'step-two') }}">D02/D03</a><a href="{{ route('dealer-registration.preview', 'success') }}">D04</a><a href="{{ route('dealer-registration.preview', 'inactive') }}">D06</a></nav>
        </aside>
    @endif
    <header class="dr-header">
        <div class="dr-container dr-header-row">
            <a class="dr-mobile-topbar" href="{{ $rules ? route('home') : ($preview ? route('dealer-registration.preview', 'landing').'#registration' : route('dealers.profile', $dealer->slug).'#registration') }}" @if (!$rules) data-back-step @endif><span aria-hidden="true">‹</span> {{ $rules ? 'Thể lệ chương trình' : ($lookup ? 'Tra cứu đăng ký' : 'Đăng ký tham gia') }}</a>
            <a href="{{ route('home') }}" class="dr-brand" aria-label="Toyota Community — Trang chủ">
                <img src="{{ asset('dealer-registration/'.($lookup || $rules ? 'lookup-logo.svg' : 'logo.png')) }}" width="{{ $lookup || $rules ? '49.8315' : '50' }}" height="36" alt="Toyota">
                <span><strong>Veloz &amp; Hilux</strong><small>Kết nối cộng đồng</small></span>
            </a>
            <nav aria-label="Điều hướng đăng ký" class="dr-header-actions">
                @if ($dealer)
                <a class="dr-lookup" href="{{ route('dealers.lookup.show', $dealer->slug) }}" @if ($lookup) aria-current="page" @endif>@if ($lookup)<img class="dr-icon" src="{{ asset('dealer-registration/lookup-search.svg') }}" width="16" height="16" alt="">@else<x-dealer-registration.icon name="search" :size="16" />@endif Tra cứu đăng ký</a>
                <span class="dr-chip">@if ($lookup)<img class="dr-icon" src="{{ asset('dealer-registration/lookup-dealer.svg') }}" width="11" height="11" alt="">@else<x-dealer-registration.icon name="dealer" :size="11" />@endif <span>Đại lý giới thiệu: <strong>{{ $dealer->name }}</strong></span></span>
                @if ($dealer->is_active)<a class="dr-button dr-button-small" href="{{ $landing ? '#registration' : ($preview ? route('dealer-registration.preview', 'landing').'#registration' : route('dealers.profile', $dealer->slug).'#registration') }}">Đăng ký ngay</a>@endif
                @else
                    <a class="dr-button dr-button-small" href="{{ route('dealers.index') }}">{{ $rules ? 'Đăng ký ngay' : 'Đăng ký tham gia' }}</a>
                @endif
            </nav>
        </div>
    </header>
    <main id="dr-main" tabindex="-1">{{ $slot }}</main>
    <footer class="dr-footer">
        <div class="dr-container dr-footer-row">
            <img src="{{ asset('dealer-registration/'.($lookup || $rules ? 'lookup-logo.svg' : 'logo.png')) }}" width="{{ $lookup || $rules ? '49.8315' : '50' }}" height="36" alt="Toyota">
            <p>{{ $rules || $lookup ? 'Chương trình của Toyota Việt Nam · Vận hành bởi Ô tô Sài Gòn' : 'Cộng đồng Toyota Veloz & Hilux' }}</p>
            <x-dealer-registration.rules-link label="Thể lệ" :new-tab="!$rules" />
            <x-dealer-registration.link :url="config('dealer-registration.privacy_url')" label="Bảo mật thông tin" :status-hint="!$rules" />
            @if ($rules)<span>Hotline 1900 xxxx</span>@elseif (config('dealer-registration.hotline'))<a href="tel:{{ preg_replace('/[^+0-9]/', '', config('dealer-registration.hotline')) }}">Hotline {{ config('dealer-registration.hotline') }}</a>@endif
        </div>
    </footer>
</body>
</html>
