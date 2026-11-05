<x-competition.layout title="Bảng xếp hạng cộng đồng · Toyota Veloz &amp; Hilux">
    <div class="wc-leaderboard">
        <section class="wc-container wc-lb-intro" aria-labelledby="leaderboard-heading">
            <div class="wc-lb-heading-row">
                <div class="wc-lb-heading">
                    <p class="wc-lb-eyebrow">TOYOTA VELOZ &amp; HILUX</p>
                    <h1 id="leaderboard-heading">Bảng xếp hạng cộng đồng</h1>
                    <p class="wc-lb-description">Vinh danh đại lý mời thành viên tham gia cộng đồng. Mỗi khai báo đã duyệt được tính 1 điểm, theo ngày khai báo.</p>
                </div>
                <nav class="wc-lb-periods" aria-label="Chọn kỳ xếp hạng">
                    @foreach ([\App\Enums\LeaderboardPeriod::Month, \App\Enums\LeaderboardPeriod::AllTime] as $option)
                        <a href="{{ route('leaderboard', ['period' => $option->value]) }}" @if ($period === $option) aria-current="page" @endif>{{ $option->label() }}</a>
                    @endforeach
                </nav>
            </div>
            <div class="wc-lb-meta">
                <p><img src="{{ asset('competition/leaderboard/calendar.svg') }}" width="16" height="16" alt="" aria-hidden="true"><span>@if ($bounds[0]){{ $bounds[0]->format('d/m/Y') }} – {{ $bounds[1]->subDay()->format('d/m/Y') }}@else{{ $period->label() }}@endif · {{ in_array(config('app.timezone'), ['Asia/Ho_Chi_Minh', 'Asia/Saigon'], true) ? 'Giờ Việt Nam' : 'Múi giờ '.config('app.timezone') }}</span></p>
                <p><img class="wc-lb-desktop" src="{{ asset('competition/leaderboard/info.svg') }}" width="16" height="16" alt="" aria-hidden="true"><img class="wc-lb-mobile" src="{{ asset('competition/leaderboard/info-mobile.svg') }}" width="16" height="16" alt="" aria-hidden="true"><span>Bằng điểm: đại lý đạt điểm sớm hơn xếp trên.</span></p>
            </div>
        </section>
        <section class="wc-container wc-lb-standings" aria-label="Top Đại Lý">
            @if ($topDealers->isEmpty())
                <div class="wc-lb-empty" role="status">Chưa có thành tích đã duyệt trong kỳ này.</div>
            @else
                <div class="wc-lb-podium">
                    @foreach ($topDealers as $leader)
                        <x-competition.leaderboard-card :leader="$leader" />
                    @endforeach
                </div>
                <section class="wc-lb-full" aria-labelledby="full-ranking-heading">
                    <h2 id="full-ranking-heading">Bảng thứ hạng đầy đủ</h2>
                    <div class="wc-lb-list">
                        @if ($dealers->getCollection()->where('rank', '>', 3)->isNotEmpty())
                        <div class="wc-lb-table-head" aria-hidden="true"><span>Hạng</span><span>Đại lý</span><span>Mã đại lý</span><span>Điểm</span></div>
                        <ol class="wc-lb-rows" aria-label="Thứ hạng đại lý">
                            @foreach ($dealers as $row)
                                @if ((int) $row->rank > 3)
                                    <li value="{{ $row->rank }}"><a class="wc-lb-row" href="{{ route('dealers.competition', $row->id) }}">
                                        <span class="wc-lb-rank"><span class="wc-lb-sr">Hạng </span>{{ $row->rank }}</span>
                                        <span class="wc-lb-name">{{ $row->name }}</span>
                                        <span class="wc-lb-code">{{ $row->code }}</span>
                                        <span class="wc-lb-score">{{ number_format($row->score, 0, ',', '.') }}<span class="wc-lb-sr"> điểm</span></span>
                                    </a></li>
                                @endif
                            @endforeach
                        </ol>
                        @else
                            <p class="wc-lb-list-note">
                                @if ($dealers->total() <= 3)
                                    Các đại lý có thành tích trong kỳ đã được hiển thị ở trên.
                                @else
                                    Không có đại lý trên trang này. <a href="{{ $dealers->url(1) }}">Về trang đầu</a>
                                @endif
                            </p>
                        @endif
                        <x-competition.leaderboard-pagination :rows="$dealers" />
                    </div>
                </section>
            @endif
        </section>
    </div>
</x-competition.layout>
