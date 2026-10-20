@props(['title', 'leaders', 'type', 'detailRoute' => null, 'avatars' => [], 'dealerIds' => [], 'publicSlugs' => []])
<div class="min-w-0 rounded-2xl border border-stone-200 bg-white p-5 sm:p-7">
    <div class="flex items-center justify-between border-b border-stone-100 pb-5">
        <h3 class="text-lg font-semibold">{{ $title }}</h3>
        <span class="text-[10px] font-semibold uppercase tracking-[0.2em] text-stone-500">Top 03</span>
    </div>
    <ol class="divide-y divide-stone-100">
        @forelse ($leaders as $leader)
            <li class="flex items-center gap-2 py-5 sm:gap-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold {{ (int) $leader->rank === 1 ? 'bg-red-50 text-red-700' : 'bg-stone-100 text-stone-500' }}" aria-label="Hạng {{ $leader->rank }}">{{ sprintf('%02d', $leader->rank) }}</span>
                @if ($type === 'sales')
                    <x-sales-avatar :sales-id="$leader->id" :has-avatar="$avatars[$leader->id] ?? false" class="h-10 w-10 sm:h-12 sm:w-12" />
                @endif
                <div class="min-w-0 grow"><p class="break-words text-sm font-semibold sm:text-base">
                    @if ($type === 'sales' && isset($publicSlugs[$leader->id]))
                        <a href="{{ route('sales.public.show', ['slug' => $publicSlugs[$leader->id]]) }}" class="rounded hover:text-red-700 hover:underline focus-visible:outline-red-600">{{ $leader->name }}</a>
                    @elseif ($detailRoute)
                        <a href="{{ route($detailRoute, $leader->id) }}" class="rounded hover:text-red-700 hover:underline focus-visible:outline-red-600">{{ $leader->name }}</a>
                    @else
                        {{ $leader->name }}
                    @endif
                </p><p class="mt-1 break-words text-xs text-stone-500">@if ($type === 'sales')<x-dealer-link :dealer-id="$dealerIds[$leader->id] ?? null" :name="$leader->dealer_name" />@else{{ $leader->code }}@endif</p>
                    @if ($type === 'sales' && isset($publicSlugs[$leader->id]) && $detailRoute)
                        <a href="{{ route($detailRoute, $leader->id) }}" class="mt-1 inline-block rounded py-1 text-xs text-red-700 hover:underline focus-visible:outline-red-600" aria-label="Chi tiết nội bộ của {{ $leader->name }}">Chi tiết nội bộ</a>
                    @endif
                </div>
                <div class="shrink-0 text-right"><p class="text-xl font-semibold tracking-tight">{{ number_format($leader->score, 0, ',', '.') }}</p><p class="mt-1 text-[10px] uppercase tracking-widest text-stone-500">điểm</p></div>
            </li>
        @empty
            <li class="py-10 text-center"><p class="font-medium text-stone-700">Những kết nối đầu tiên đang chờ bạn.</p><p class="mt-2 text-sm leading-6 text-stone-500">Chưa có thành tích đã duyệt trong kỳ này.</p></li>
        @endforelse
    </ol>
</div>
