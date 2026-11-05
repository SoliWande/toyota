@props(['dealerProfile' => false, 'mobileTitle' => null])
@php($accountUrl = auth()->check() ? route(auth()->user()->homeRoute()) : route('login'))
<header class="wc-header"><div class="wc-container wc-header-row">
        @if ($mobileTitle)<a class="wc-account-mobile-back" href="{{ route('login') }}"><x-competition.dealer-icon name="back-22" :size="22" />{{ $mobileTitle }}</a>@endif
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
