@props(['awards', 'selectedAward' => null, 'periodType' => ''])
@php($hasOtherPeriods = $awards->contains(fn ($item) => $item->id !== $selectedAward?->id))
<aside class="wc-aw-history {{ $hasOtherPeriods ? '' : 'wc-aw-no-previous' }}" aria-label="Lịch sử các kỳ đã công bố">
    <h2 class="wc-aw-history-heading">{{ $hasOtherPeriods ? ($selectedAward?->id === $awards->first()?->id ? 'Các kỳ trước' : 'Các kỳ đã công bố') : 'Loại kỳ vinh danh' }}</h2>
    @if ($awards->isNotEmpty())
        <nav class="wc-aw-period-list" aria-label="Các kỳ vinh danh đã công bố">
            @foreach ($awards as $periodAward)
                @php($active = $selectedAward?->id === $periodAward->id)
                @php($leader = $periodAward->winners->firstWhere('rank', 1))
                <a class="wc-aw-period-link {{ $active ? 'is-selected' : '' }}" href="{{ route('awards.show', ['award' => $periodAward->id, ...($periodType === '' ? [] : ['period_type' => $periodType]), ...($awards->currentPage() === 1 ? [] : ['page' => $awards->currentPage()])]) }}" @if ($active) aria-current="page" @endif>
                    <span class="wc-aw-period-icon"><img class="wc-aw-desktop" src="{{ asset('competition/awards/'.($active ? 'period-active-16' : 'period-16').'.svg') }}" width="16" height="16" alt="" aria-hidden="true"><img class="wc-aw-mobile" src="{{ asset('competition/awards/period-18.svg') }}" width="18" height="18" alt="" aria-hidden="true"></span>
                    <span class="wc-aw-period-copy"><strong><x-competition.award-period :award="$periodAward" /></strong><span>
                        @if ($leader && filled($leader->dealer_name_snapshot))
                            {{ $leader->dealer_name_snapshot }} dẫn đầu
                        @else
                            Đã công bố kết quả
                        @endif
                    </span></span>
                    <img class="wc-aw-history-arrow wc-aw-mobile" src="{{ asset('competition/awards/chevron-18.svg') }}" width="18" height="18" alt="" aria-hidden="true">
                </a>
            @endforeach
        </nav>
    @endif
    <nav class="wc-aw-filter" aria-label="Loại kỳ vinh danh">
        @foreach (['' => 'Tất cả', 'weekly' => 'Tuần', 'monthly' => 'Tháng'] as $value => $label)
            <a href="{{ route('awards.index', $value === '' ? [] : ['period_type' => $value]) }}" @if ($periodType === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @if ($awards->hasPages())
        <nav class="wc-aw-pagination" aria-label="Phân trang kỳ vinh danh">
            @if ($awards->onFirstPage())<span aria-disabled="true">‹ Trước</span>@else<a href="{{ $awards->previousPageUrl() }}" rel="prev">‹ Trước</a>@endif
            <span>Trang {{ $awards->currentPage() }} / {{ $awards->lastPage() }}</span>
            @if ($awards->hasMorePages())<a href="{{ $awards->nextPageUrl() }}" rel="next">Sau ›</a>@else<span aria-disabled="true">Sau ›</span>@endif
        </nav>
    @endif
</aside>
