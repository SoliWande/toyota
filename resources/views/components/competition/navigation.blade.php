@props(['mobile' => false])
@foreach (['Trang chủ' => route('home'), 'Bảng xếp hạng' => route('leaderboard'), 'Vinh danh' => route('awards.index'), ($mobile ? 'Đại lý tham gia' : 'Đại lý') => route('dealers.index'), 'Thể lệ' => route('home').'#the-le'] as $label => $href)
    <a href="{{ $href }}" @if (($loop->first && request()->routeIs('home')) || ($loop->index === 1 && request()->routeIs('leaderboard')) || ($loop->index === 2 && request()->routeIs('awards.*'))) aria-current="page" @endif>@if ($mobile)<x-competition.icon :name="['nav-home', 'nav-ranking', 'nav-awards', 'nav-dealers', 'nav-rules'][$loop->index]" />@endif<span>{{ $label }}</span>@if ($mobile)<x-competition.icon name="chevron" :size="16" />@endif</a>
@endforeach
