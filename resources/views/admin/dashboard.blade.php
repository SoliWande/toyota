<x-admin.layout title="Tổng quan">
    @vite('resources/css/admin-overview.css')
    @php
        $number = fn ($value) => number_format($value, 0, ',', '.');
        $pendingUrl = route('admin.submissions.index', ['status' => 'pending']);
        $leaders = $ranking === 'sales' ? $topSales : $topDealers;
        $dayNames = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
    @endphp
    <div class="ao-page">
        <header class="ao-head">
            <div><p class="ao-eyebrow">KHÔNG GIAN QUẢN TRỊ</p><h1>Xin chào, {{ auth()->user()->name }}</h1><p>Theo dõi chương trình và xử lý các khai báo đang chờ duyệt.</p></div>
            <div class="ao-date"><img src="{{ asset('admin/icons/c01-calendar.png') }}" alt="" width="16" height="16">{{ $dayNames[$now->dayOfWeek] }}, {{ $now->format('d/m/Y') }}</div>
        </header>
        <section class="ao-tasks" aria-label="Việc cần làm">
            <div class="ao-review">
                <img class="ao-review-art" src="{{ asset('admin/icons/c01-hero.png') }}" alt="">
                <h2>Duyệt khách hàng</h2>
                <div class="ao-review-total"><strong>{{ $number($overview['pending']) }}</strong><div><p>khách chờ xác minh</p>@if ($overview['oldest'])<small>Cũ nhất: {{ $overview['oldest']->format('d/m/Y') }} · {{ $number($overview['oldest_days']) }} ngày</small>@endif</div></div>
                <span class="ao-overdue"><svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true"><defs><filter id="ao-clock-alpha" color-interpolation-filters="sRGB"><feColorMatrix type="matrix" values="0 0 0 0 1 0 0 0 0 1 0 0 0 0 1 0 0 -1.080508474576 0 1" /></filter><mask id="ao-clock" style="mask-type:alpha"><image href="{{ asset('admin/icons/c01-clock.png') }}" width="14" height="14" filter="url(#ao-clock-alpha)" /></mask></defs><rect width="14" height="14" fill="white" mask="url(#ao-clock)" /></svg>{{ $number($overview['overdue']) }} khai báo quá 48 giờ</span>
                <x-admin.button :href="$pendingUrl" variant="secondary" size="42" class="ao-start">Bắt đầu duyệt<img src="{{ asset('admin/icons/c01-arrow.png') }}" width="16" height="16" alt=""></x-admin.button>
            </div>
            <div class="ao-accounts ao-card">
                <h2><span class="ao-account-icon"><x-admin.icon name="registration" size="18" /></span>Duyệt tài khoản</h2>
                <div class="ao-account-total"><strong>{{ $number($overview['pending_managers']) }}</strong><p>quản lý đại lý<br>chờ duyệt</p></div>
                <p class="ao-sales-warning"><img src="{{ asset('admin/icons/c01-clock.png') }}" width="14" height="14" alt="">Thêm {{ $number($overview['overdue_sales']) }} sales chờ quá 3 ngày</p>
                <a class="ao-link" href="{{ route('admin.dealer-registrations.index') }}">Mở danh sách<img src="{{ asset('admin/icons/c01-arrow.png') }}" width="14" height="14" alt=""></a>
            </div>
            <nav class="ao-shortcuts ao-card" aria-label="Lối tắt">
                <h2>Lối tắt</h2>
                @foreach ([['dealer', 'Đại lý', route('admin.dealers.index')], ['trophy', 'Tạo kỳ vinh danh', route('admin.awards.index')], ['external', 'Xem bảng xếp hạng', route('leaderboard')]] as [$icon, $label, $url])
                    <a href="{{ $url }}" @if ($icon === 'external') target="_blank" rel="noopener" @endif><span><x-admin.icon :name="$icon" size="16" /></span><p>{{ $label }}</p><img src="{{ asset('admin/icons/c01-chevron.png') }}" width="16" height="16" alt=""></a>
                @endforeach
            </nav>
        </section>
        <div class="ao-stats-head"><h2>Tổng quan chương trình</h2><p>Bấm vào số để mở danh sách đã lọc</p></div>
        <section class="ao-stats" aria-label="Tổng quan chương trình">
            <div class="ao-kpis">
                <div class="ao-kpi ao-card"><div class="ao-kpi-label"><span>CHỈ SỐ CHÍNH</span><h3>Khách đã xác minh tháng này</h3></div><div class="ao-kpi-value"><strong>{{ $number($overview['approved_month']) }}</strong><p>khách · tháng {{ $monthStart->format('m/Y') }}</p></div><div class="ao-track" aria-hidden="true"><i></i></div></div>
                <div class="ao-kpi ao-card"><div class="ao-kpi-label"><span>CHỈ SỐ PHỤ</span><h3>Đại lý có từ 5 khách đã xác minh</h3></div><div class="ao-kpi-value"><strong>{{ $number($overview['qualified_dealers']) }}</strong><p>/ {{ $number($stats['dealers']) }} đại lý tham gia</p></div><div class="ao-track ao-track-secondary" aria-hidden="true"><i></i></div></div>
            </div>
            @foreach ([['dealers' => 'Tổng đại lý', 'active_dealers' => 'Đại lý hoạt động', 'sales' => 'Tổng sales', 'active_sales' => 'Sales hoạt động', 'pending_sales' => 'Sales chờ duyệt'], ['submissions' => 'Tổng khách hàng', 'approved_submissions' => 'Khách đã duyệt', 'pending_submissions' => 'Chờ xác minh', 'week_submissions' => 'Khai báo tuần này', 'month_submissions' => 'Khai báo tháng này']] as $row)
                <dl class="ao-stat-row">
                    @foreach ($row as $key => $label)
                        <div class="ao-stat ao-card"><dt>{{ $label }}<img src="{{ asset('admin/icons/c01-chevron.png') }}" width="14" height="14" alt=""></dt><dd><a href="{{ $statLinks[$key] }}" data-stat="{{ $key }}" @class(['ao-green' => $key === 'approved_submissions', 'ao-red' => $key === 'pending_submissions'])>{{ $number($stats[$key]) }}</a></dd>@if (in_array($key, ['week_submissions', 'month_submissions']))<small>Mọi trạng thái</small>@endif</div>
                    @endforeach
                </dl>
            @endforeach
        </section>
        <section class="ao-bottom">
            <div class="ao-queue ao-card">
                <div class="ao-queue-head"><h2>Chờ xác minh gần đây</h2><a class="ao-link" href="{{ $pendingUrl }}">Xem toàn bộ hàng chờ<img src="{{ asset('admin/icons/c01-arrow.png') }}" width="14" height="14" alt=""></a></div>
                @foreach ($recentPendingSubmissions as $submission)
                    @php
                        $digits = preg_replace('/\D/', '', $submission->phone ?? '');
                        $maskedPhone = strlen($digits) >= 9 && strlen($digits) <= 15 ? substr($digits, 0, 2).str_repeat('*', strlen($digits) - 6).substr($digits, -4) : '';
                    @endphp
                    <div class="ao-queue-row">
                        <div class="ao-customer"><strong>{{ $submission->customer_name }}</strong><small>{{ $maskedPhone }}</small></div>
                        <div class="ao-attribution"><strong>{{ $submission->dealer?->name }}</strong><small>{{ $submission->source === 'dealer_qr' ? 'Khách tự đăng ký' : $submission->sales?->name }}</small></div>
                        <div class="ao-source"><x-admin.badge :variant="$submission->source === 'dealer_qr' ? 'neutral' : 'info'">{{ $submission->source === 'dealer_qr' ? 'QR đại lý' : 'Sales khai báo' }}</x-admin.badge></div>
                        <time datetime="{{ $submission->submitted_at->toIso8601String() }}">{{ $submission->submitted_at->format('d/m H:i') }}</time>
                        <x-admin.button :href="route('admin.submissions.show', $submission)" size="32">Duyệt</x-admin.button>
                    </div>
                @endforeach
            </div>
            <div class="ao-ranking ao-card" id="ranking">
                <h2>Dẫn đầu tháng này</h2><p class="ao-ranking-period">{{ $monthStart->format('d/m') }} – {{ $monthEnd->subDay()->format('d/m/Y') }} · Chỉ tính khai báo đã duyệt</p>
                <x-admin.tabs :items="[['key' => 'dealers', 'label' => 'Đại lý', 'href' => route('admin.dashboard', ['ranking' => 'dealers']).'#ranking'], ['key' => 'sales', 'label' => 'Sales (nội bộ)', 'href' => route('admin.dashboard', ['ranking' => 'sales']).'#ranking']]" :active="$ranking" />
                @foreach ($leaders as $leader)
                    <a @class(['ao-leader', 'ao-leader-first' => $loop->first]) href="{{ route($ranking === 'sales' ? 'admin.sales.show' : 'admin.dealers.show', $leader->id) }}"><span class="ao-rank">@if ($loop->first)<img src="{{ asset('admin/icons/c01-winner.png') }}" width="15" height="15" alt="">@else{{ $leader->rank }}@endif</span><div><strong>{{ $leader->name }}</strong><small>{{ $ranking === 'sales' ? $leader->dealer_name : $leader->code }}</small></div><b>{{ $number($leader->score) }}</b></a>
                @endforeach
            </div>
        </section>
    </div>
</x-admin.layout>
