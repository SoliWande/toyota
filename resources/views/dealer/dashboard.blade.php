<x-competition.account-layout :title="'Dashboard · '.$dealer->name" :staff="true" staff-page="b09">
    <header class="wc-staff-title"><div><p>QUẢN LÝ ĐẠI LÝ</p><h1>{{ $dealer->name }}</h1><small>Xin chào, {{ auth()->user()->name }} · Theo dõi và phát triển cộng đồng tại đại lý.</small></div><a class="wc-staff-button" href="{{ route('dealer.customers.index') }}">Xem khách hàng →</a></header>
    @if (session('success'))<p class="wc-auth-success" role="status">{{ session('success') }}</p>@endif
    @if ($errors->any())<div class="wc-auth-error" role="alert">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <div class="wc-dashboard-top">
        <section class="wc-staff-card wc-rank-card"><p>HẠNG THÁNG {{ now()->format('m') }} · {{ config('app.timezone') }}</p><div class="wc-dealer-rank-top"><h2 @class(['wc-no-rank' => !$standings['month']->rank])>{{ $standings['month']->rank ? '#'.$standings['month']->rank : 'Chưa có thứ hạng' }}</h2><strong>{{ number_format($standings['month']->score) }}<small>điểm hợp lệ</small></strong></div>
            <div class="wc-rank-distance">
                @if ($rankAhead)
                    Thêm {{ number_format(max(1, $rankAhead->score - $standings['month']->score + 1)) }} hồ sơ được duyệt để vượt điểm {{ $rankAhead->name }} (#{{ $rankAhead->rank }}).
                @elseif ($standings['month']->rank)
                    Đại lý đang dẫn đầu bảng xếp hạng tháng.
                @else
                    Chưa có hồ sơ được duyệt trong tháng.
                @endif
            </div><a href="{{ route('leaderboard', ['period' => 'month']) }}">Xem bảng xếp hạng →</a></section>
        <section class="wc-staff-card wc-funnel-card"><header><h2>Phễu tháng này</h2><p>Theo tháng đăng ký · Mỗi hồ sơ thuộc một nhóm</p></header>
            @php($funnelTotal = array_sum($registrationReport['counts']))
            <dl class="wc-funnel">
                @foreach (\App\Services\DealerRegistrationReport::STAGES as $key => $label)
                    <div class="wc-funnel-row wc-funnel-{{ $key }}"><dt>{{ $label }}</dt><dd><span class="wc-funnel-track" aria-hidden="true"><span style="width: {{ $funnelTotal ? round($registrationReport['counts'][$key] * 100 / $funnelTotal, 2) : 0 }}%"></span></span><strong>{{ number_format($registrationReport['counts'][$key]) }}</strong></dd></div>
                @endforeach
            </dl>
            <p class="wc-report-note">{{ number_format($registrationReport['rejected']) }} hồ sơ từ chối trong tháng, thống kê riêng ngoài phễu. Đã xác minh là hồ sơ được Admin duyệt.</p>
        </section>
    </div>
    <div class="wc-dashboard-middle">
        <section class="wc-staff-card"><header class="wc-staff-section-title"><h2>Khách đang kẹt <span>{{ number_format($registrationReport['stalled_count']) }}</span></h2><span class="wc-stalled-badge">Quá 24 giờ</span></header>
            <p class="wc-report-note">Hồ sơ đang chờ toàn kỳ · Tối đa 5 hồ sơ chờ lâu nhất</p>
            @forelse ($registrationReport['stalled'] as $customer)
                <a class="wc-customer-row" href="{{ route('dealer.customers.show', $customer['id']) }}"><span><strong>{{ $customer['name'] }}</strong><small>{{ $customer['stage'] }} · Từ {{ $customer['since']->format('d/m/Y H:i') }}</small></span><span>Xem →</span></a>
            @empty<p class="wc-staff-empty">Không có hồ sơ quá 24 giờ ở bước đang chờ.</p>@endforelse
            @if ($registrationReport['missing_clock'])<p class="wc-report-note">{{ number_format($registrationReport['missing_clock']) }} hồ sơ chưa đủ mốc thời gian để xác định quá hạn.</p>@endif
            <a href="{{ route('dealer.customers.index') }}">Xem tất cả khách hàng →</a>
        </section>
        <section class="wc-staff-card"><header><h2>Thi đua nội bộ</h2><p>Điểm được duyệt trong tháng</p></header>
            @forelse ($staffStandings as $person)<div class="wc-staff-standing"><span class="wc-staff-initial">{{ mb_substr($person->name, 0, 1) }}</span><strong>{{ $person->name }}</strong><b>{{ number_format($person->score) }}</b></div>@empty<p class="wc-staff-empty">Chưa có điểm hợp lệ trong tháng.</p>@endforelse
            <a href="{{ route('dealer.sales.index') }}">Xem nhân viên →</a>
        </section>
    </div>
    <div class="wc-dashboard-bottom">
        <x-competition.pending-staff :people="$pendingSales" :count="$pendingSalesCount" />
        <section class="wc-staff-card wc-qr-card"><div><h2>QR đại lý</h2><p>Mời khách hàng vào cộng đồng qua mã QR của đại lý.</p><div class="wc-qr-actions"><a class="wc-staff-button" href="{{ route('dealer.standee') }}">Tải standee A4 (PDF)</a><a href="{{ route('dealers.show', $dealer->id) }}">Xem hồ sơ đại lý →</a></div></div><a href="{{ route('dealers.qr', [$dealer->id, 'download' => 1]) }}"><img src="{{ route('dealers.qr', $dealer->id) }}" alt="Mã QR đăng ký tại {{ $dealer->name }}" width="104" height="104"><span>Tải QR PNG</span></a></section>
    </div>
    <section class="wc-staff-card wc-staff-history"><header><h2>Thành tích đã công bố</h2></header>@forelse ($awards as $winner)<p>{{ $winner->award->period_start->format('d/m/Y') }} · Hạng {{ $winner->rank }} · {{ number_format($winner->score) }} điểm</p>@empty<p>Chưa có thành tích được công bố.</p>@endforelse<a href="{{ route('dealer.awards.index') }}">Xem lịch sử →</a></section>
</x-competition.account-layout>
