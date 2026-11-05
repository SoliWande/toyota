@props(['dealers', 'filters' => [], 'provinces' => []])
@php
    $sort = $filters['sort'] ?? 'members';
    $direction = $filters['direction'] ?? ($sort === 'members' ? 'desc' : 'asc');
    $nextDirection = $direction === 'asc' ? 'desc' : 'asc';
    $sortLabels = ['members' => 'Thành viên', 'name' => 'Tên đại lý', 'rank' => '#Hạng'];
    $currentProvince = $filters['province'] ?? '';
    $flipState = array_filter(['q' => $filters['q'] ?? '', 'province' => $currentProvince, 'sort' => $sort, 'direction' => $nextDirection], fn ($value) => $value !== '');
@endphp
<x-competition.layout title="Đại lý tham gia | Toyota Competition" :sticky-footer="true">
<section class="wc-container wc-d-directory" aria-labelledby="wc-d-title">
    <header class="wc-d-intro"><h1 id="wc-d-title">Đại lý tham gia</h1><p>{{ number_format($dealers->total(), 0, ',', '.') }} đại lý {{ filled($filters['q'] ?? '') || filled($filters['province'] ?? '') ? 'phù hợp' : 'trên toàn quốc' }}</p></header>
    <form class="wc-d-filters" action="{{ route('dealers.index') }}" method="GET">
        <div class="wc-d-search"><button type="submit" aria-label="Tìm kiếm đại lý"><x-competition.dealer-icon name="search-20" :size="20" /></button><label class="wc-d-sr" for="wc-d-search">Tên đại lý hoặc tỉnh / thành phố</label><input id="wc-d-search" name="q" maxlength="255" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm tên đại lý" type="search"></div>
        <label class="wc-d-province"><span class="wc-d-sr">Tỉnh / thành phố</span><select name="province" data-dealer-province @if (count($provinces) === 0) aria-describedby="wc-d-province-note" @endif><option value="">Tất cả tỉnh thành</option>@if ($currentProvince !== '' && !in_array($currentProvince, collect($provinces)->all(), true))<option value="{{ $currentProvince }}" selected>{{ $currentProvince }} — không có đại lý</option>@endif @foreach ($provinces as $province)<option value="{{ $province }}" @selected($currentProvince === $province)>{{ $province }}</option>@endforeach</select><span class="wc-d-desktop"><x-competition.dealer-icon name="down-16" :size="16" /></span><span class="wc-d-mobile"><x-competition.dealer-icon name="down-14" :size="14" /></span></label>
        <div class="wc-d-sort-controls"><label class="wc-d-sort"><span class="wc-d-sr">Sắp xếp theo</span><select name="sort" data-dealer-sort>@foreach ($sortLabels as $value => $label)<option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>@endforeach</select><span class="wc-d-desktop"><x-competition.dealer-icon name="down-16" :size="16" /></span><span class="wc-d-mobile"><x-competition.dealer-icon name="down-14" :size="14" /></span></label>
        <input name="direction" type="hidden" value="{{ $direction }}" data-dealer-direction-value>
        <a class="wc-d-direction" href="{{ route('dealers.index', $flipState) }}" data-dealer-direction="{{ $nextDirection }}" aria-label="Đảo chiều: đổi sang {{ $nextDirection === 'asc' ? 'tăng dần' : 'giảm dần' }}" title="Đang {{ $direction === 'asc' ? 'tăng dần' : 'giảm dần' }}; bấm để đảo chiều"><span aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span></a></div>
    </form>
    @if (count($provinces) === 0)<p id="wc-d-province-note" class="wc-d-filter-note" role="status">Chưa có dữ liệu tỉnh/thành của đại lý để lựa chọn.</p>@endif
    <div class="wc-d-list">
        <div class="wc-d-list-head" aria-hidden="true"><span>ĐẠI LÝ</span><span>TỈNH / THÀNH</span><span>THÀNH VIÊN</span><span>#Hạng</span><span></span></div>
        @forelse ($dealers as $dealer)
        <a class="wc-d-row" href="{{ route('dealers.competition', $dealer->id) }}">
            <span class="wc-d-name"><span class="wc-d-store"><x-competition.dealer-icon name="store-18" :size="18" /></span><strong>{{ $dealer->name }}</strong></span>
            <span class="wc-d-meta"><span class="wc-d-province-copy"><span class="wc-d-desktop"><x-competition.dealer-icon name="pin-16" :size="16" /></span><span class="wc-d-mobile"><x-competition.dealer-icon name="pin-13" :size="13" /></span>{{ $dealer->province ?: 'Chưa cập nhật tỉnh/thành' }}</span>
            <span class="wc-d-score" aria-label="{{ number_format($dealer->members_count) }} thành viên (hồ sơ đã duyệt)"><span class="wc-d-mobile"><x-competition.dealer-icon name="users-13" :size="13" /></span><strong>{{ number_format($dealer->members_count, 0, ',', '.') }}</strong><span>thành viên</span></span></span>
            <span class="wc-d-rank" aria-label="#Hạng: {{ $dealer->rank ?: 'chưa xếp hạng' }}, toàn kỳ"><span @class(['wc-d-badge', 'wc-d-first' => (int) $dealer->rank === 1])><span class="wc-d-rank-label">#Hạng </span><span class="wc-d-rank-hash">{{ $dealer->rank ? '#' : '' }}</span>{{ $dealer->rank ?: '—' }}</span></span>
            <span class="wc-d-next wc-d-desktop"><x-competition.dealer-icon name="right-18" :size="18" /></span><span class="wc-d-next wc-d-mobile"><x-competition.dealer-icon name="right-16" :size="16" /></span>
        </a>
        @empty
        <div class="wc-d-empty"><x-competition.dealer-icon name="store-18" :size="18" /><h2>Không tìm thấy đại lý phù hợp.</h2><p>Thử tìm tên đại lý hoặc tỉnh/thành khác.</p>@if (filled($filters['q'] ?? '') || filled($filters['province'] ?? ''))<a class="wc-button wc-button-dark" href="{{ route('dealers.index') }}">Xem tất cả đại lý</a>@endif</div>
        @endforelse
    </div>
    <x-competition.dealer-pagination :rows="$dealers" />
</section>
</x-competition.layout>
