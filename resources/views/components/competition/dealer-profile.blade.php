@props(['dealer', 'standing' => null, 'months' => [], 'phone' => null, 'photoUrl' => null])
<x-competition.layout :title="$dealer->name.' | Toyota Competition'" :sticky-footer="true" :dealer-profile="true">
<article class="wc-container wc-d-profile">
    <a class="wc-d-back" href="{{ route('dealers.index') }}"><x-competition.dealer-icon name="back-18" :size="18" />Tất cả đại lý</a>
    <div class="wc-d-banner">@if ($photoUrl)<img class="wc-d-photo" src="{{ $photoUrl }}" alt="{{ $dealer->name }}" width="1200" height="280">@else<span class="wc-d-desktop"><x-competition.dealer-icon name="image-36" :size="36" /></span><span class="wc-d-mobile"><x-competition.dealer-icon name="image-28" :size="28" /></span><p>Ảnh đại lý</p>@endif</div>
    <div class="wc-d-profile-heading"><div><h1>{{ $dealer->name }}</h1><p>{{ $dealer->code }} · {{ $dealer->province }}</p>@unless ($dealer->is_active)<p class="wc-d-inactive">Đại lý hiện ngừng nhận đăng ký.</p>@endunless</div>
    @if ($dealer->is_active)<a class="wc-button wc-button-red wc-d-join wc-d-desktop" href="{{ route('dealers.profile', $dealer->slug) }}#registration"><x-competition.dealer-icon name="arrow-18" :size="18" />Đăng ký tham gia cùng đại lý</a>@endif</div>
    <div class="wc-d-profile-grid"><div class="wc-d-performance">
        <div class="wc-d-stats"><section class="wc-d-stat"><span class="wc-d-stat-symbol"><span class="wc-d-desktop"><x-competition.dealer-icon name="users-24" :size="24" /></span><span class="wc-d-mobile"><x-competition.dealer-icon name="users-16" :size="16" /></span></span><div><strong>{{ number_format($standing->score ?? 0, 0, ',', '.') }}</strong><p>Thành viên đã xác minh</p></div></section>
        <a class="wc-d-stat" href="{{ route('leaderboard', ['period' => 'all']) }}"><span class="wc-d-stat-symbol"><span class="wc-d-desktop"><x-competition.dealer-icon name="trophy-24" :size="24" /></span><span class="wc-d-mobile"><x-competition.dealer-icon name="trophy-16" :size="16" /></span></span><div><strong>{{ $standing ? '#'.$standing->rank : '—' }}</strong><p>{{ $standing ? 'Hạng toàn chương trình' : 'Chưa xếp hạng' }}</p></div></a></div>
        <section class="wc-d-chart"><h2>Thành viên mới 3 tháng gần nhất</h2><x-competition.dealer-chart :months="$months" /></section>
        @if ($dealer->is_active)<a class="wc-button wc-button-red wc-d-join wc-d-mobile" href="{{ route('dealers.profile', $dealer->slug) }}#registration">Đăng ký tham gia cùng đại lý</a>@endif
    </div>
    <aside class="wc-d-contact" aria-label="Liên hệ đại lý"><h2>LIÊN HỆ ĐẠI LÝ</h2>
        <div class="wc-d-contact-row"><span class="wc-d-desktop"><x-competition.dealer-icon name="pin-20" :size="20" /></span><span class="wc-d-mobile"><x-competition.dealer-icon name="pin-18" :size="18" /></span><p>{{ $dealer->address ?: 'Chưa cập nhật địa chỉ' }}</p></div>
        @if ($phone)<div class="wc-d-contact-row"><span class="wc-d-desktop"><x-competition.dealer-icon name="phone-20" :size="20" /></span><span class="wc-d-mobile"><x-competition.dealer-icon name="phone-18" :size="18" /></span><a href="tel:{{ $phone }}" aria-label="Gọi đại lý {{ $dealer->name }}">{{ $dealer->phone }}</a></div>@endif
        @if (filled($dealer->opening_hours))<div class="wc-d-contact-row"><span class="wc-d-desktop"><x-competition.dealer-icon name="clock-20" :size="20" /></span><span class="wc-d-mobile"><x-competition.dealer-icon name="clock-18" :size="18" /></span><p>{{ $dealer->opening_hours }}</p></div>@endif
        @if ($phone)<a class="wc-d-call" href="tel:{{ $phone }}"><x-competition.dealer-icon name="phone-20" :size="20" />Gọi đại lý</a>@endif
    </aside></div>
</article>
</x-competition.layout>
