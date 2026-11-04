@props(['awards', 'selectedAward' => null, 'periodType' => '', 'detail' => false])
<x-competition.layout title="Lịch sử vinh danh · Toyota Veloz &amp; Hilux" :sticky-footer="true">
    <div class="wc-container wc-awards">
        <header class="wc-aw-intro"><p>DẤU ẤN CỘNG ĐỒNG</p><h1>Lịch sử vinh danh</h1><p>Top 3 đại lý mỗi kỳ thi đua, công bố sau khi admin chốt kỳ.</p></header>
        @if ($awards->isNotEmpty())
            <nav class="wc-aw-chips" aria-label="Chọn kỳ vinh danh">
                @foreach ($awards as $periodAward)
                    <a href="{{ route('awards.show', ['award' => $periodAward->id, ...($periodType === '' ? [] : ['period_type' => $periodType]), ...($awards->currentPage() === 1 ? [] : ['page' => $awards->currentPage()])]) }}" @if ($selectedAward?->id === $periodAward->id) aria-current="page" @endif><x-competition.award-period :award="$periodAward" /></a>
                @endforeach
            </nav>
        @endif
        <div class="wc-aw-split">
            <x-competition.award-history :awards="$awards" :selected-award="$selectedAward" :period-type="$periodType" />
            <div class="wc-aw-selected">
                <x-competition.award-panel :award="$selectedAward" />
                @if ($detail && $selectedAward)
                    <div class="wc-aw-snapshot-note">
                        <p>{{ $selectedAward->period_start->format('d/m/Y') }} – {{ $selectedAward->period_end->subDay()->format('d/m/Y') }} · Công bố {{ $selectedAward->published_at->format('d/m/Y H:i') }}</p>
                        <p>Kết quả đã lưu tại thời điểm công bố. Thành tích được duyệt sau đó không thay đổi lịch sử này.</p>
                        <a href="{{ route('awards.index', $periodType === '' ? [] : ['period_type' => $periodType]) }}">← Lịch sử vinh danh</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-competition.layout>
