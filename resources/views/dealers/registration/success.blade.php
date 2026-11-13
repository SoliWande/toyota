@php
    // Only the session-authorized confirmation DTO supplies the answers below.
    $confirmation = $confirmation ?? null;
    $callPhone = app(\App\Services\DealerCallPhone::class)->forDealer($dealer);
@endphp
<x-dealer-registration.layout :dealer="$dealer" title="Đăng ký thành công!" :success="true" :preview="$preview ?? false">
    <div class="dr-container dr-success-layout">
        <section class="dr-success-main dr-card">
            <div class="dr-success-heading">
                <img src="{{ asset('dealer-registration/success.png') }}" width="96" height="96" alt="">
                <div><h1>Đăng ký thành công!</h1><p>Ban quản trị sẽ đối chiếu và duyệt bạn vào group.</p></div>
            </div>
            <div class="dr-group">
                <picture class="dr-group-picture">
                    <source media="(max-width: 767px)" srcset="{{ asset('dealer-registration/group-mobile.png') }}">
                    <img class="dr-group-photo" src="{{ asset('dealer-registration/group.png') }}" width="300" height="240" alt="Các chủ xe Toyota cùng tham gia chuyến đi">
                </picture>
                <div class="dr-group-copy">
                    <h2>Cộng đồng Toyota Veloz &amp; Hilux</h2>
                    <div class="dr-group-questions">
                        <strong><x-dealer-registration.icon name="info" :size="18" /> Chưa xin vào group? Khi xin, bạn sẽ được hỏi:</strong>
                        <ol>
                            <li>Số điện thoại đã đăng ký: {{ $confirmation['phone'] ?? 'Chưa có dữ liệu xác nhận' }}</li>
                            <li>Biển số xe: {{ $confirmation['license_plate'] ?? 'Chưa có dữ liệu xác nhận' }}</li>
                        </ol>
                        <p>Trả lời giống như trên để được duyệt nhanh.</p>
                    </div>
                    <div class="dr-group-action">
                        <x-dealer-registration.link :url="config('dealer-registration.group_url')" :group="true" label="Chưa xin vào group? Vào ngay" class="dr-button" />
                        <p class="dr-help">Dùng đúng tài khoản Facebook bạn đã khai báo.</p>
                    </div>
                </div>
            </div>
        </section>
        <aside class="dr-sidebar dr-success-sidebar">
            <section class="dr-side-card dr-success-progress">
                <div class="dr-progress-heading"><h2>Trạng thái đăng ký</h2><span class="dr-status-chip">Chờ duyệt</span></div>
                <ol class="dr-timeline" aria-label="Hướng dẫn tham gia cộng đồng">
                    <li class="is-neutral"><strong>Vào group Facebook</strong><p>Đã xin ở bước 1? Nếu chưa, vào group ngay ở trên.</p></li>
                    <li class="is-done"><strong>Đăng ký thông tin</strong><p>Đã nhận thông tin của bạn</p></li>
                    <li class="is-current" aria-current="step"><strong>Thành viên chính thức</strong><p>Đang chờ duyệt, trong vòng 48 giờ.</p></li>
                </ol>
            </section>
            <a href="{{ route('dealers.lookup.show', $dealer->slug) }}" class="dr-success-lookup" aria-label="Tra cứu trạng thái đăng ký">
                <span class="dr-success-lookup-icon"><img src="{{ asset('dealer-registration/success-sidebar-search.png') }}" width="18" height="18" alt=""></span>
                <span class="dr-success-lookup-copy"><strong>Tra cứu trạng thái đăng ký</strong><span class="dr-success-desktop-caption">Lưu lại trang này để xem sau</span><span class="dr-success-mobile-caption">Chụp màn hình lại để xem sau</span></span>
                <img class="dr-success-chevron" src="{{ asset('dealer-registration/success-sidebar-chevron.png') }}" width="18" height="18" alt="">
            </a>
            <section class="dr-success-dealer" aria-label="Đại lý giới thiệu">
                <span class="dr-success-dealer-icon"><img src="{{ asset('dealer-registration/success-sidebar-dealer.png') }}" width="18" height="18" alt=""></span>
                <div class="dr-success-dealer-copy"><small>Đăng ký qua đại lý</small><h2>{{ $dealer->name }}</h2></div>
                @if ($callPhone)<a href="tel:{{ $callPhone }}" class="dr-success-call" aria-label="Gọi đại lý"><img src="{{ asset('dealer-registration/success-sidebar-phone.png') }}" width="18" height="18" alt=""></a>@endif
            </section>
        </aside>
    </div>
</x-dealer-registration.layout>
