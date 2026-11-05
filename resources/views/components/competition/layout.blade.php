@props(['title' => 'Thi đua cộng đồng Toyota Veloz & Hilux', 'stickyFooter' => false, 'dealerProfile' => false])
@php($accountUrl = auth()->check() ? route(auth()->user()->homeRoute()) : route('login'))
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Cùng các đại lý Toyota kết nối chủ xe với cộng đồng Veloz & Hilux. Lan toả giá trị, ghi nhận nỗ lực.">
    <title>{{ $title }}</title>
    <link rel="stylesheet" href="{{ asset('dealer-registration/lookup-fonts.css') }}">
    @vite(['resources/css/competition.css', 'resources/js/competition.js'])
</head>
<body @class(['competition-site', 'wc-sticky-footer' => $stickyFooter, 'wc-d-profile-site' => $dealerProfile])>
    <a class="wc-skip" href="#noi-dung">Bỏ qua điều hướng, đến nội dung</a>
    <header class="wc-header"><div class="wc-container wc-header-row">
        @if ($dealerProfile)<a class="wc-d-header-back" href="{{ route('dealers.index') }}"><x-competition.dealer-icon name="back-22" :size="22" />Đại lý</a>@endif
        <x-competition.brand />
        <nav class="wc-desktop-nav" aria-label="Điều hướng chính"><x-competition.navigation /></nav>
        <a class="wc-login" href="{{ $accountUrl }}">{{ auth()->check() ? 'Tài khoản' : 'Đăng nhập' }}</a>
        <details class="wc-menu" data-competition-menu>
            <summary aria-label="Mở menu điều hướng" aria-controls="wc-mobile-menu"><x-competition.icon name="menu" /><span class="wc-menu-close"><x-competition.icon name="close" /></span></summary>
            <nav id="wc-mobile-menu" class="wc-mobile-panel" aria-label="Điều hướng mobile">
                <img class="wc-menu-logo" src="{{ asset('dealer-registration/lookup-logo.svg') }}" width="49.8315" height="36" alt="Toyota">
                <x-competition.navigation :mobile="true" />
                <div class="wc-mobile-account">
                    <a class="wc-button wc-button-dark" href="{{ $accountUrl }}">{{ auth()->check() ? 'Tài khoản của tôi' : 'Đăng nhập cho nhân viên' }}</a>
                    @auth
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="wc-logout" type="submit">Đăng xuất</button></form>
                    @else
                        <p>Chủ xe không cần tài khoản, chỉ cần quét mã QR tại đại lý.</p>
                    @endauth
                </div>
            </nav>
        </details>
    </div></header>
    <main id="noi-dung" tabindex="-1">{{ $slot }}</main>
    <footer class="wc-footer"><div class="wc-container wc-footer-row">
        <img src="{{ asset('dealer-registration/lookup-logo.svg') }}" width="49.8315" height="36" alt="Toyota">
        <p>Chương trình của Toyota Việt Nam · Vận hành bởi Ô tô Sài Gòn</p>
        <a href="{{ route('campaign.rules') }}">Thể lệ</a>
        @php($privacyUrl = config('dealer-registration.privacy_url'))
        @if (is_string($privacyUrl) && preg_match('~^https?://~i', $privacyUrl))<a href="{{ $privacyUrl }}">Bảo mật thông tin</a>@else<span aria-disabled="true">Bảo mật thông tin</span>@endif
        @php($hotline = config('dealer-registration.hotline'))
        @if (is_string($hotline) && preg_match('/\A(?:0[35789][0-9]{8}|02[0-9]{9}|1[89]00[0-9]{4,6})\z/', \App\Services\RegistrationPhone::normalize($hotline)))<a href="tel:{{ \App\Services\RegistrationPhone::normalize($hotline) }}">Hotline {{ $hotline }}</a>@endif
    </div></footer>
</body>
</html>
