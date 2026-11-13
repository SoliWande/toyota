<section class="dr-hero" aria-labelledby="dr-hero-title">
    <picture class="dr-hero-art"><source media="(max-width: 639px)" srcset="{{ asset('dealer-registration/hero-mobile.png') }}"><img src="{{ asset('dealer-registration/hero-desktop.png') }}" width="1440" height="680" alt="Toyota Veloz và Hilux trên cung đường núi lúc hoàng hôn" fetchpriority="high"></picture>
    <div class="dr-container dr-hero-inner"><img class="dr-mobile-logo" src="{{ asset('dealer-registration/logo.png') }}" width="60" height="44" alt="Toyota"><div class="dr-hero-copy">
    <div class="dr-hero-dealer"><span class="dr-hero-dealer-icon"><picture><source media="(max-width: 639px)" srcset="{{ asset('dealer-registration/hero-dealer-mobile.png') }}"><img src="{{ asset('dealer-registration/hero-dealer-desktop.png') }}" width="18" height="18" alt=""></picture></span><div><small>Đăng ký qua đại lý</small><strong>{{ $dealer->name }}</strong></div></div>
    <p class="dr-eyebrow">VELOZ · HILUX</p><h1 id="dr-hero-title">KẾT NỐI<br>HÀNH TRÌNH</h1><p class="dr-hero-description">Cộng đồng chủ xe Toyota. Giao lưu, chia sẻ và cùng nhau lên đường.</p>
    <section class="dr-hero-perks" aria-labelledby="dr-hero-perks-title">
        <h2 id="dr-hero-perks-title">Quyền lợi khi tham gia</h2>
        <ul>
            @foreach (['gift' => 'Logo & quà tặng', 'service' => 'Ưu đãi bảo dưỡng', 'chat' => 'Chia sẻ kinh nghiệm', 'expert' => 'Chuyên gia khám xe', 'event' => 'Sự kiện & chuyến đi'] as $icon => $benefit)
                <li><span class="dr-hero-perk-icon"><picture><source media="(max-width: 639px)" srcset="{{ asset('dealer-registration/hero-'.$icon.'-mobile.png') }}"><img src="{{ asset('dealer-registration/hero-'.$icon.'-desktop.png') }}" width="18" height="18" alt=""></picture></span><span>{{ $benefit }}</span></li>
            @endforeach
        </ul>
    </section>
    <div class="dr-hero-capsule"><a href="#registration" class="dr-button">Tham gia ngay <x-dealer-registration.icon name="arrow" /></a>
    @if (isset($dealerOwnerCount))<p class="dr-hero-count"><strong>{{ number_format($dealerOwnerCount, 0, ',', '.') }} chủ xe</strong><small>đã tham gia</small></p>@endif
    </div></div></div>
</section>
<x-dealer-registration.step-one :dealer="$dealer" :preview="$preview" :validation-errors="$stepOneErrors ?? []" :values="$stepOneValues ?? []" :duplicate-phone="$duplicatePhone ?? false" />
<section class="dr-benefits dr-section" aria-labelledby="dr-benefits-title"><div class="dr-container"><h2 id="dr-benefits-title">Lợi ích dành cho chủ xe khi tham gia</h2></div><div class="dr-container dr-benefit-grid" aria-label="Quyền lợi cộng đồng" tabindex="0">
@foreach ([['gift', 'Đăng ký thành viên để được nhận logo cộng đồng và các phần quà'], ['service', 'Ưu đãi đặc quyền khi bảo dưỡng dành cho thành viên chính thức'], ['sharing', 'Chia sẻ kinh nghiệm sử dụng xe, bảo dưỡng, nâng cấp'], ['diagnostics', 'Chẩn đoán sự cố và khám “bệnh” từ chuyên gia Toyota'], ['events', 'Tham gia các sự kiện và chuyến đi']] as [$benefit, $heading])
    <article class="dr-benefit"><img class="dr-benefit-photo" src="{{ asset('dealer-registration/benefits-five-'.$benefit.'-photo.png') }}" width="228" height="284" alt="{{ $heading }}" loading="lazy"><div class="dr-benefit-body"><span class="dr-benefit-icon"><img src="{{ asset('dealer-registration/benefits-five-'.$benefit.'-icon.png') }}" width="20" height="20" alt="" aria-hidden="true"></span><h3>{{ $heading }}</h3></div></article>
@endforeach
</div></section>
<section class="dr-container dr-section"><div class="dr-journey"><div><h2>3 bước là xong</h2><p>Vào group bằng Facebook của bạn, sau đó điền thông tin khoảng 1 phút.</p></div>
@foreach ([['Vào group Facebook', 'Quét QR, xin vào group và trả lời số điện thoại, biển số xe.'], ['Đăng ký thông tin', 'Tên, số điện thoại và thông tin xe, khoảng 1 phút.'], ['Thành viên chính thức', 'Ban quản trị đối chiếu và duyệt.']] as [$heading, $copy])<article><span>{{ $loop->iteration }}</span><h3>{{ $heading }}</h3><p>{{ $copy }}</p></article>@endforeach
</div></section>
<div class="dr-container dr-section dr-information"><section id="dr-rules" aria-labelledby="dr-rules-title"><h2 id="dr-rules-title">Thể lệ</h2>
<div class="dr-dates" aria-labelledby="dr-dates-title">
    <div class="dr-dates-heading"><img src="{{ asset('dealer-registration/rules-calendar.png') }}" width="16" height="16" alt=""><small id="dr-dates-title">THỜI GIAN ÁP DỤNG</small></div>
    <div class="dr-dates-range"><div class="dr-dates-start"><span>Bắt đầu</span><strong><time datetime="2026-11-01">01.11.2026</time></strong></div><span class="dr-dates-line" aria-hidden="true"></span><div class="dr-dates-end"><span>Kết thúc</span><strong><time datetime="2027-01-31">31.01.2027</time></strong></div></div>
</div>
<div class="dr-rules-list">@foreach (['Đối tượng tham gia' => 'Chủ xe Toyota đăng ký trực tiếp với đại lý để được xác minh thông tin.', 'Điều kiện được xác nhận' => 'Thông tin đăng ký cần được ban quản trị kiểm tra và duyệt.', 'Quyền lợi thành viên' => 'Quyền lợi cụ thể theo thông báo của chương trình và đại lý.'] as $q => $a)<details @if ($loop->first) open @endif><summary>{{ $q }}<span aria-hidden="true"></span></summary><p>{{ $a }}</p></details>@endforeach</div><x-dealer-registration.rules-link label="Xem thể lệ đầy đủ →" class="dr-rules-link" /></section>
<section aria-labelledby="dr-faq-title"><h2 id="dr-faq-title">Hỏi nhanh</h2><div class="dr-faq">@foreach (['Tham gia có mất phí không?' => 'Không. Tham gia hoàn toàn miễn phí.', 'Thông tin của tôi dùng vào việc gì?' => 'Chỉ để xác nhận bạn là chủ xe và để đại lý liên hệ khi cần. Thông tin không được hiển thị công khai.', 'Bao lâu thì được xác nhận?' => 'Đại lý và ban quản trị sẽ kiểm tra thông tin. Bạn có thể liên hệ đại lý để được hỗ trợ.'] as $q => $a)<div class="dr-question">{{ $q }}</div><div class="dr-answer"><span aria-hidden="true"><img src="{{ asset('dealer-registration/logo.png') }}" width="18" height="13" alt=""></span><p>{{ $a }}</p></div>@endforeach</div></section></div>
<div class="dr-container dr-section dr-dealer-section"><x-dealer-registration.dealer-card :dealer="$dealer" />
</div>
@if ($dealer->is_active)<div class="dr-sticky-cta"><a class="dr-button" href="#registration">Tham gia ngay <x-dealer-registration.icon name="arrow" /></a></div>@endif
