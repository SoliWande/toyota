@props(['award' => null])
<section class="wc-aw-panel" aria-labelledby="award-period-heading" @if ($award) data-award-id="{{ $award->id }}" @endif>
    <img class="wc-aw-glow wc-aw-desktop" src="{{ asset('competition/awards/glow-desktop.svg') }}" width="640" height="640" alt="" aria-hidden="true">
    <img class="wc-aw-glow wc-aw-mobile" src="{{ asset('competition/awards/glow-mobile.svg') }}" width="440" height="440" alt="" aria-hidden="true">
    @if ($award)
        <div class="wc-aw-panel-heading">
            <p>KỲ VINH DANH</p>
            <h2 id="award-period-heading"><x-competition.award-period :award="$award" /></h2>
            <p class="wc-aw-published">Công bố <time datetime="{{ $award->published_at->toIso8601String() }}" title="{{ $award->published_at->format('d/m/Y H:i') }}">{{ $award->published_at->format('d/m/Y') }}</time> · {{ in_array(config('app.timezone'), ['Asia/Ho_Chi_Minh', 'Asia/Saigon'], true) ? 'Giờ Việt Nam' : 'Múi giờ '.config('app.timezone') }}</p>
        </div>
        <div class="wc-aw-winners" aria-label="Đại lý được vinh danh">
            @foreach ($award->winners as $winner)
                <article class="wc-aw-winner wc-aw-rank-{{ $winner->rank }}" data-award-rank="{{ $winner->rank }}">
                    <span class="wc-aw-medal"><img class="wc-aw-desktop" src="{{ asset('competition/awards/trophy-22.svg') }}" width="22" height="22" alt="" aria-hidden="true"><img class="wc-aw-mobile" src="{{ asset('competition/awards/trophy-18.svg') }}" width="18" height="18" alt="" aria-hidden="true"></span>
                    <div class="wc-aw-winner-copy">
                        <p class="wc-aw-rank">HẠNG {{ $winner->rank }}</p>
                        <h3><x-dealer-link :dealer-id="$winner->dealer_id" :name="filled($winner->dealer_name_snapshot) ? $winner->dealer_name_snapshot : 'Chưa có thông tin tên đại lý'" :public="true" /></h3>
                        <p class="wc-aw-score">@if (filled($winner->dealer_code_snapshot)){{ $winner->dealer_code_snapshot }} · @endif{{ $winner->score === null ? 'Chưa có thông tin điểm' : number_format($winner->score, 0, ',', '.').' điểm' }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <div class="wc-aw-empty" role="status">
            <span class="wc-aw-medal"><img src="{{ asset('competition/awards/trophy-22.svg') }}" width="22" height="22" alt="" aria-hidden="true"></span>
            <h2 id="award-period-heading">Chưa có kết quả vinh danh được công bố.</h2>
        </div>
    @endif
</section>
