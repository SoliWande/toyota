@props(['mobile' => false])
@foreach (['Trang chủ' => route('home'), 'Bảng xếp hạng' => route('leaderboard'), 'Vinh danh' => route('awards.index'), 'Thể lệ' => route('home').'#the-le'] as $label => $href)
    <a href="{{ $href }}" @if ($label === 'Trang chủ' && request()->routeIs('home')) aria-current="page" @endif
        class="{{ $mobile ? 'block border-b border-stone-100 py-4' : 'py-3' }} text-sm font-medium text-stone-600 transition-colors hover:text-red-700">{{ $label }}</a>
@endforeach
