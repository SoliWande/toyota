@props(['title', 'leaders', 'type'])
<div class="min-w-0 rounded-2xl border border-stone-200 bg-white p-5 sm:p-7">
    <div class="flex items-center justify-between border-b border-stone-100 pb-5">
        <h3 class="text-lg font-semibold">{{ $title }}</h3>
        <span class="text-[10px] font-semibold uppercase tracking-[0.2em] text-stone-500">Top 03</span>
    </div>
    <ol class="divide-y divide-stone-100">
        @forelse ($leaders as $leader)
            <li class="flex items-center gap-4 py-5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold {{ (int) $leader->rank === 1 ? 'bg-red-50 text-red-700' : 'bg-stone-100 text-stone-500' }}" aria-label="Hạng {{ $leader->rank }}">{{ sprintf('%02d', $leader->rank) }}</span>
                <div class="min-w-0 grow"><p class="break-words text-sm font-semibold sm:text-base">{{ $leader->name }}</p><p class="mt-1 break-words text-xs text-stone-500">{{ $type === 'sales' ? $leader->dealer_name : $leader->code }}</p></div>
                <div class="shrink-0 text-right"><p class="text-xl font-semibold tracking-tight">{{ number_format($leader->score, 0, ',', '.') }}</p><p class="mt-1 text-[10px] uppercase tracking-widest text-stone-500">điểm</p></div>
            </li>
        @empty
            <li class="py-10 text-center"><p class="font-medium text-stone-700">Những kết nối đầu tiên đang chờ bạn.</p><p class="mt-2 text-sm leading-6 text-stone-500">Chưa có thành tích đã duyệt trong kỳ này.</p></li>
        @endforelse
    </ol>
</div>
