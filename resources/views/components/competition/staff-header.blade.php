@php
    $user = auth()->user();
    $manager = $user->role === \App\Enums\UserRole::Dealer;
    $links = $manager ? [
        ['dealer.dashboard', 'Bảng điều khiển'], ['dealer.customers.index', 'Khách của tôi'], ['dealer.sales.index', 'Nhân viên'],
    ] : [['sales.dashboard', 'Dashboard'], ['sales.submissions.create', 'Khai báo khách'], ['sales.submissions.index', 'Khách của tôi']];
    $links = array_merge($links, [['leaderboard', 'Bảng xếp hạng'], ['campaign.rules', 'Thể lệ']]);
@endphp
<header class="wc-header wc-staff-header"><div class="wc-container wc-header-row">
    @if (request()->routeIs('dealer.sales.*'))<a class="wc-account-mobile-back" href="{{ route('dealer.dashboard') }}"><x-competition.dealer-icon name="back-22" :size="22" />Nhân viên</a>@endif
    <x-competition.brand />
    <nav class="wc-desktop-nav" aria-label="Điều hướng nhân viên">
        @foreach ($links as [$route, $label])<a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>@if ($loop->first && $manager)<a href="{{ route('dealers.show', $user->dealer_id) }}">Hồ sơ &amp; link mời</a>@endif @endforeach
    </nav>
    <details class="wc-staff-account-menu"><summary class="wc-staff-account"><span class="wc-staff-initial">{{ mb_substr($user->name, 0, 1) }}</span><span><strong>{{ $user->name }}</strong><small>{{ $manager ? 'Quản lý đại lý' : 'Nhân viên kinh doanh' }}</small></span></summary><div class="wc-staff-account-dropdown"><a href="{{ route('profile.edit') }}">Hồ sơ cá nhân</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="wc-status-logout">Đăng xuất</button></form></div></details>
    <details class="wc-menu" data-competition-menu>
        <summary aria-label="Mở menu điều hướng" aria-controls="wc-mobile-menu"><x-competition.icon name="menu" /><span class="wc-menu-close"><x-competition.icon name="close" /></span></summary>
        <nav id="wc-mobile-menu" class="wc-mobile-panel" aria-label="Điều hướng nhân viên mobile">
            <div class="wc-staff-menu-person"><span class="wc-staff-initial">{{ mb_substr($user->name, 0, 1) }}</span><strong>{{ $user->name }}</strong><p>{{ $manager ? 'Quản lý đại lý' : 'Nhân viên kinh doanh' }} · {{ $user->dealer?->name }}</p></div>
            @foreach ($links as [$route, $label])<a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>@if ($loop->first && $manager)<a href="{{ route('dealers.show', $user->dealer_id) }}">Hồ sơ &amp; link mời</a>@endif @endforeach
            <a href="{{ route('profile.edit') }}">Hồ sơ cá nhân</a><a href="{{ route('home') }}">Trang chủ</a><a href="{{ route('awards.index') }}">Vinh danh</a><a href="{{ route('dealers.index') }}">Đại lý</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="wc-status-logout">Đăng xuất</button></form>
        </nav>
    </details>
</div></header>
