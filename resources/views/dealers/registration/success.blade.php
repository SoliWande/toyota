@php
    // Only the session-authorized confirmation DTO supplies the answers below.
    $confirmation = $confirmation ?? null;
@endphp
<x-dealer-registration.layout :dealer="$dealer" title="Đăng ký thành công!" :success="true" :preview="$preview ?? false">
    <div class="dr-container dr-success-layout">
        <section class="dr-success-main dr-card">
            <div class="dr-success-heading">
                <img src="{{ asset('dealer-registration/success.png') }}" width="96" height="96" alt="">
                <div><h1>Đăng ký thành công!</h1><p>Bước cuối: vào group Facebook của nhóm.</p></div>
            </div>
            <div class="dr-group">
                <picture class="dr-group-picture">
                    <source media="(max-width: 767px)" srcset="{{ asset('dealer-registration/group-mobile.png') }}">
                    <img class="dr-group-photo" src="{{ asset('dealer-registration/group.png') }}" width="300" height="240" alt="Các chủ xe Toyota cùng tham gia chuyến đi">
                </picture>
                <div class="dr-group-copy">
                    <h2>Cộng đồng Toyota Veloz &amp; Hilux</h2>
                    <div class="dr-group-questions">
                        <strong><x-dealer-registration.icon name="info" :size="18" /> Khi xin vào group, bạn sẽ được hỏi:</strong>
                        <ol>
                            <li>Số điện thoại đã đăng ký: {{ $confirmation['phone'] ?? 'Chưa có dữ liệu xác nhận' }}</li>
                            <li>Biển số xe: {{ $confirmation['license_plate'] ?? 'Chưa có dữ liệu xác nhận' }}</li>
                        </ol>
                        <p>Trả lời đúng thông tin bạn đã đăng ký để được quản trị viên nhóm kiểm tra.</p>
                    </div>
                    <div class="dr-group-action">
                        <x-dealer-registration.link :url="config('dealer-registration.group_url')" :group="true" label="Tham gia group Facebook" class="dr-button" />
                        <p class="dr-help">Dùng tài khoản Facebook bạn đã khai báo.</p>
                    </div>
                </div>
            </div>
        </section>
        <aside class="dr-sidebar">
            <section class="dr-side-card dr-success-progress">
                <div class="dr-progress-heading"><h2>Trạng thái đăng ký</h2><span class="dr-status-chip">Chờ vào group</span></div>
                <ol class="dr-timeline" aria-label="Hướng dẫn tham gia cộng đồng">
                    <li class="is-done"><strong>Đăng ký thông tin</strong><p>Đã nhận thông tin của bạn</p></li>
                    <li class="is-current" aria-current="step"><strong>Vào group Facebook</strong><p>Bạn đang ở bước này</p></li>
                    <li class="is-upcoming"><strong>Thành viên chính thức</strong><p>Sau khi quản trị viên nhóm chấp nhận</p></li>
                </ol>
            </section>
            <section class="dr-side-card"><a href="{{ route('dealers.lookup.show', $dealer->slug) }}" class="dr-text-link">Tra cứu trạng thái đăng ký</a></section>
            <x-dealer-registration.dealer-card :dealer="$dealer" :compact="true" />
        </aside>
    </div>
</x-dealer-registration.layout>
