<x-competition.layout>
    @php
        $joinUrl = auth()->check() ? route(auth()->user()->homeRoute()) : route('register');
        $campaignDates = '[Chờ TMV]';
        try {
            if (config('dealer-registration.campaign_start') && config('dealer-registration.campaign_end')) {
                $campaignDates = \Carbon\CarbonImmutable::parse(config('dealer-registration.campaign_start'))->format('d/m/Y').' – '.\Carbon\CarbonImmutable::parse(config('dealer-registration.campaign_end'))->format('d/m/Y');
            }
        } catch (\Throwable $exception) {
            // Missing or invalid campaign configuration never becomes a made-up schedule.
        }
    @endphp
    <section class="wc-hero" aria-labelledby="hero-heading">
        <picture class="wc-hero-art"><source media="(max-width: 767px)" srcset="{{ asset('competition/hero-mobile.png') }}"><img src="{{ asset('competition/hero-desktop.png') }}" width="1440" height="680" alt="" fetchpriority="high"></picture>
        <div class="wc-container"><div class="wc-hero-copy">
            <p class="wc-hero-label">CỘNG ĐỒNG VELOZ &amp; HILUX</p>
            <h1 id="hero-heading">Mỗi kết nối.<br>Một hành trình mới.</h1>
            <p class="wc-hero-description">Cùng các đại lý Toyota kết nối chủ xe với cộng đồng<span class="wc-mobile-break"></span> Veloz &amp; Hilux. Lan toả giá trị, ghi nhận nỗ lực.</p>
            <div class="wc-hero-actions"><a class="wc-button" href="{{ $joinUrl }}"><x-competition.icon name="join" :size="18" />Tham gia ngay</a><a class="wc-button wc-button-glass" href="#hanh-trinh"><span class="wc-desktop-text">Khám phá chương trình</span><span class="wc-mobile-text">Khám phá</span></a></div>
            <p class="wc-hero-note">Dành cho nhân viên tại các đại lý Toyota.</p>
        </div></div>
    </section>

    <section class="wc-container wc-stats" aria-label="Chương trình qua những con số">
        <a class="wc-stat" href="{{ route('dealers.index') }}"><span class="wc-stat-icon"><x-competition.icon name="dealer" /></span><strong>{{ number_format($stats['dealers'], 0, ',', '.') }}</strong><span>Đại lý đang hoạt động</span></a>
        <div class="wc-stat"><span class="wc-stat-icon"><x-competition.icon name="members" /></span><strong>{{ number_format($stats['points'], 0, ',', '.') }}</strong><span>Thành viên đã xác minh</span></div>
        <div class="wc-stat"><span class="wc-stat-icon"><x-competition.icon name="trophy" /></span><strong class="wc-stat-pending">[Chờ TMV]</strong><span>Giải thưởng mỗi tháng <span class="wc-award-note">[Cơ cấu giải – chờ TMV]</span></span></div>
        <div class="wc-stat"><span class="wc-stat-icon"><x-competition.icon name="calendar" /></span><strong class="wc-stat-pending">{{ $campaignDates }}</strong><span>Thời gian chương trình</span></div>
    </section>

    <section id="hanh-trinh" class="wc-container wc-section" aria-labelledby="journey-heading">
        <x-competition.heading eyebrow="CÙNG BẮT ĐẦU" id="journey-heading">Từ lời mời nhỏ.<span class="wc-mobile-break"></span> Đến cộng đồng lớn.</x-competition.heading>
        <div class="wc-steps">
            @foreach ([['Đăng ký tham gia', 'Tạo tài khoản nhân viên, chọn đại lý Toyota và chờ duyệt.'], ['Lấy mã QR của đại lý', 'Tải QR / standee để trưng tại showroom, hoặc gửi link cho khách qua Zalo.'], ['Khách đăng ký & vào group', 'Khách tự quét QR và điền thông tin, hoặc nhân viên khai báo giúp.'], ['Ghi nhận & vinh danh', 'Admin xác minh. Thành viên đã duyệt được tính vào bảng xếp hạng đại lý.']] as [$title, $description])
                <div class="wc-step"><span>{{ sprintf('%02d', $loop->iteration) }}</span><div><h3>{{ $title }}</h3><p>{{ $description }}</p></div></div>
            @endforeach
        </div>
    </section>

    <section class="wc-container wc-section wc-results" aria-label="Xếp hạng và vinh danh">
        <div id="xep-hang" class="wc-ranking" aria-labelledby="ranking-heading">
            <x-competition.heading eyebrow="NHỮNG NGƯỜI DẪN NHỊP" id="ranking-heading">Top đại lý tháng này</x-competition.heading>
            <ol class="wc-ranking-list">
                @forelse ($topDealers as $dealer)
                    <li><span class="wc-rank">@if ((int) $dealer->rank === 1)<x-competition.icon name="winner" />@else{{ $dealer->rank }}@endif</span><div class="wc-ranking-name"><a href="{{ route('dealers.show', $dealer->id) }}">{{ $dealer->name }}</a><p>{{ $dealer->code }}</p></div><p class="wc-points"><strong>{{ number_format($dealer->score, 0, ',', '.') }}</strong><span>điểm</span></p></li>
                @empty
                    <li class="wc-empty">Chưa có thành tích hợp lệ trong kỳ này.</li>
                @endforelse
            </ol>
            <div class="wc-ranking-footer"><p>Theo ngày khai báo · {{ config('app.timezone') === 'Asia/Ho_Chi_Minh' ? 'Giờ Việt Nam' : 'Múi giờ '.config('app.timezone') }}</p><a class="wc-button wc-button-secondary" href="{{ route('leaderboard', ['period' => $period->value]) }}">Xem bảng xếp hạng đầy đủ</a></div>
        </div>
        <aside class="wc-award {{ $latestAward ? 'wc-award-published' : '' }}" aria-labelledby="award-heading">
            <span class="wc-award-icon"><x-competition.icon name="award" :size="34" /></span>
            @if ($latestAward)
                <h2 id="award-heading">Kỳ vinh danh gần nhất</h2><p>{{ $latestAward->period_type->label() }} · {{ $latestAward->period_start->format('d/m/Y') }} – {{ $latestAward->period_end->subDay()->format('d/m/Y') }}<br>Công bố {{ $latestAward->published_at->format('d/m/Y') }}</p>
                <ul>@foreach ($latestAward->winners as $winner)<li><span>{{ $winner->rank }}. @if ($winner->dealer_id)<a href="{{ route('dealers.show', $winner->dealer_id) }}">{{ $winner->dealer_name_snapshot }}</a>@else{{ $winner->dealer_name_snapshot }}@endif<br>{{ $winner->dealer_code_snapshot }}</span><strong>{{ number_format($winner->score, 0, ',', '.') }} điểm</strong></li>@endforeach</ul>
                <a class="wc-button wc-button-secondary" href="{{ route('awards.show', $latestAward) }}">Xem kỳ vinh danh</a>
            @else
                <h2 id="award-heading">Kết nối hôm nay.<br>Tạo nên dấu ấn ngày mai.</h2><p>Chưa có kỳ vinh danh được công bố.</p>
            @endif
        </aside>
    </section>

    <x-competition.rules :campaign-dates="$campaignDates" />

    <section class="wc-container wc-section wc-join" aria-labelledby="join-heading"><div class="wc-join-card"><h2 id="join-heading">Cộng đồng lớn hơn.<span class="wc-mobile-break"></span> Bắt đầu từ lời mời của bạn.</h2><a class="wc-button wc-button-white" href="{{ $joinUrl }}"><x-competition.icon name="join-red" :size="18" />Tham gia ngay</a></div></section>
</x-competition.layout>
