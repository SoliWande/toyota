@props(['page' => null])
@php
    $user = auth()->user();
    $manager = $user->role === \App\Enums\UserRole::Dealer;
    $links = $manager ? [
        ['dealer.dashboard', 'Bảng điều khiển'], ['dealer.customers.index', 'Khách của tôi'], ['dealer.sales.index', 'Nhân viên'],
    ] : [['sales.dashboard', 'Hồ sơ & link mời'], ['sales.submissions.create', 'Khai báo khách'], ['sales.submissions.index', 'Khách của tôi']];
    $links = array_merge($links, [['leaderboard', 'Bảng xếp hạng'], ['campaign.rules', 'Thể lệ']]);
    $isCurrent = static fn (string $route) => request()->routeIs($route) || match ($route) {
        'sales.submissions.index' => request()->routeIs('sales.submissions.show', 'sales.submissions.edit', 'sales.submissions.saved'),
        'dealer.customers.index' => request()->routeIs('dealer.customers.*'),
        'dealer.sales.index' => request()->routeIs('dealer.sales.*'),
        default => false,
    };
@endphp
<header class="wc-header wc-staff-header"><div class="wc-container wc-header-row">
    @if (request()->routeIs('dealer.sales.*'))<a class="wc-account-mobile-back" href="{{ route('dealer.dashboard') }}"><x-competition.dealer-icon name="back-22" :size="22" />Nhân viên</a>@endif
    @if (request()->routeIs('sales.submissions.create', 'sales.submissions.edit', 'sales.submissions.saved', 'sales.submissions.show'))<a class="wc-account-mobile-back" href="{{ route('sales.submissions.index') }}"><x-competition.dealer-icon name="back-22" :size="22" />{{ request()->routeIs('sales.submissions.create', 'sales.submissions.edit') ? 'Khai báo khách' : (request()->routeIs('sales.submissions.saved') ? 'Đã lưu' : 'Chi tiết khách') }}</a>@endif
    <x-competition.brand />
    <nav class="wc-desktop-nav" aria-label="Điều hướng nhân viên">
        @foreach ($links as [$route, $label])@continue(!$manager && $route === 'sales.submissions.create')<a href="{{ route($route) }}" @if ($isCurrent($route)) aria-current="page" @endif>{{ $label }}</a>@if ($loop->first && $manager)<a href="{{ route('dealers.show', $user->dealer_id) }}">Hồ sơ &amp; link mời</a>@endif @endforeach
    </nav>
    @if (!$manager)<a class="wc-staff-cta" href="{{ route('sales.submissions.create') }}" @if (request()->routeIs('sales.submissions.create')) aria-current="page" @endif><span aria-hidden="true">+</span>Khai báo khách</a>@endif
    <details class="wc-staff-account-menu"><summary class="wc-staff-account">@if ($manager)<x-dealer-avatar :user="$user" class="wc-staff-initial" />@else<span class="wc-staff-initial">{{ mb_substr($user->name, 0, 1) }}</span>@endif<span class="wc-staff-account-copy"><strong title="{{ $user->name }}">{{ $user->name }}</strong><small>{{ $page === 'b08' && !$manager ? 'Sales · '.$user->dealer?->name : ($manager ? 'Quản lý đại lý' : 'Nhân viên kinh doanh') }}</small></span><span class="wc-staff-account-chevron" aria-hidden="true">⌄</span></summary><div class="wc-staff-account-dropdown"><a href="{{ route('profile.edit') }}">Hồ sơ cá nhân</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="wc-status-logout">Đăng xuất</button></form></div></details>
    <details class="wc-menu" data-competition-menu>
        <summary aria-label="Mở menu điều hướng" aria-controls="wc-mobile-menu"><x-competition.icon name="menu" /><span class="wc-menu-close"><x-competition.icon name="close" /></span></summary>
        <nav id="wc-mobile-menu" class="wc-mobile-panel" aria-label="Điều hướng nhân viên mobile">
            <div class="wc-staff-menu-person">@if ($manager)<x-dealer-avatar :user="$user" class="wc-staff-initial" />@else<span class="wc-staff-initial">{{ mb_substr($user->name, 0, 1) }}</span>@endif<strong>{{ $user->name }}</strong><p>{{ $manager ? 'Quản lý đại lý' : 'Nhân viên kinh doanh' }} · {{ $user->dealer?->name }}</p></div>
            @foreach ($links as [$route, $label])<a href="{{ route($route) }}" @if ($isCurrent($route)) aria-current="page" @endif>{{ $label }}</a>@if ($loop->first && $manager)<a href="{{ route('dealers.show', $user->dealer_id) }}">Hồ sơ &amp; link mời</a>@endif @endforeach
            <a href="{{ route('profile.edit') }}">Hồ sơ cá nhân</a><a href="{{ route('home') }}">Trang chủ</a><a href="{{ route('awards.index') }}">Vinh danh</a><a href="{{ route('dealers.index') }}">Đại lý</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="wc-status-logout">Đăng xuất</button></form>
        </nav>
    </details>
</div></header>
