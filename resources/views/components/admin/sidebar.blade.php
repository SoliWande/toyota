@props(['user', 'section', 'counts'])
@php
    $items = [
        ['overview', 'Tổng quan', 'overview', 'admin.dashboard'],
        ['submissions', 'Duyệt khách', 'check', 'admin.submissions.index'],
        ['group', 'Tra SĐT vào group', 'search', null],
        ['registrations', 'Duyệt tài khoản', 'registration', 'admin.dealer-registrations.index'],
        ['dealers', 'Đại lý', 'dealer', 'admin.dealers.index'],
        ['sales', 'Sales', 'sales', 'admin.sales.index'],
        ['awards', 'Vinh danh', 'trophy', 'admin.awards.index'],
        ['sampling', 'Kiểm tra ngẫu nhiên', 'phone', null],
    ];
    $initials = collect(preg_split('/\s+/u', trim($user->name)))->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->join('');
@endphp
<aside class="ad-sidebar" aria-label="Quản trị">
    <div class="ad-brand"><img src="{{ asset('dealer-registration/lookup-logo.svg') }}" width="50" height="36" alt="Toyota"><div><p>Quản trị</p><small>Cộng đồng Veloz &amp; Hilux</small></div></div>
    <nav class="ad-nav" aria-label="Quản trị">
        @foreach ($items as [$key, $label, $icon, $route])
            @if ($route)
                <a href="{{ route($route) }}" class="ad-nav-item" @if ($key === $section) aria-current="page" @endif><x-admin.icon :name="$icon" /><span>{{ $label }}</span>@if (array_key_exists($key, $counts))<b>{{ number_format($counts[$key], 0, ',', '.') }}</b>@endif</a>
            @else
                <span class="ad-nav-item" aria-disabled="true"><x-admin.icon :name="$icon" /><span>{{ $label }}</span></span>
            @endif
        @endforeach
        <div class="ad-nav-divider"></div>
        <a href="{{ route('leaderboard') }}" class="ad-nav-item" target="_blank" rel="noopener noreferrer"><x-admin.icon name="globe" /><span>Bảng xếp hạng</span><x-admin.icon name="external" :size="16" /></a>
    </nav>
    <div class="ad-account">
        <a class="ad-account-person" href="{{ route('profile.edit') }}"><span class="ad-avatar">{{ $initials }}</span><span class="ad-account-copy"><strong title="{{ $user->name }}">{{ $user->name }}</strong><small>Admin Ô tô Sài Gòn</small></span><x-admin.icon name="down" :size="16" /></a>
        {{-- Existing logout is retained explicitly; Figma does not specify an account menu. --}}
        <form method="POST" action="{{ route('logout') }}" class="ad-logout">@csrf<button type="submit">Đăng xuất</button></form>
    </div>
</aside>
