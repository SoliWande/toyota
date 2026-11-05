<x-competition.account-layout :title="'Dashboard · '.$dealer->name" :staff="true">
    <header class="wc-staff-title"><div><p>QUẢN LÝ ĐẠI LÝ</p><h1>{{ $dealer->name }}</h1><small>Xin chào, {{ auth()->user()->name }} · Theo dõi và phát triển cộng đồng tại đại lý.</small></div><a class="wc-staff-button" href="{{ route('dealer.customers.index') }}">Xem khách hàng →</a></header>
    @if (session('success'))<p class="wc-auth-success" role="status">{{ session('success') }}</p>@endif
    @if ($errors->any())<div class="wc-auth-error" role="alert">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <div class="wc-dashboard-top">
        <section class="wc-staff-card wc-rank-card"><p>XẾP HẠNG ĐẠI LÝ · THÁNG NÀY</p><h2>{{ $standings['month']->rank ? '#'.$standings['month']->rank : 'Chưa có thứ hạng' }}</h2><strong>{{ number_format($standings['month']->score) }} <small>điểm hợp lệ</small></strong><a href="{{ route('leaderboard', ['period' => 'month']) }}">Xem bảng xếp hạng →</a></section>
        <section class="wc-staff-card wc-progress-card"><header><h2>Tổng quan đại lý</h2><p>Số liệu thực tế trên hệ thống</p></header><dl>
            <div><dt>Đã khai báo</dt><dd>{{ number_format($customerCount) }}</dd></div><div><dt>Đã duyệt toàn kỳ</dt><dd>{{ number_format($standings['all']->score) }}</dd></div><div><dt>Nhân viên</dt><dd><a href="{{ route('dealer.sales.index') }}">{{ number_format($salesCount) }}</a></dd></div>
        </dl><p>Điểm chỉ tính hồ sơ đã được Admin duyệt. #Hạng toàn kỳ: {{ $standings['all']->rank ? '#'.$standings['all']->rank : '—' }}.</p></section>
    </div>
    <div class="wc-dashboard-middle">
        <section class="wc-staff-card"><header class="wc-staff-section-title"><h2>Khách hàng gần đây</h2><a href="{{ route('dealer.customers.index') }}">Xem tất cả →</a></header>
            @forelse ($recentCustomers as $customer)<a class="wc-customer-row" href="{{ route('dealer.customers.show', $customer) }}"><span><strong>{{ $customer->customer_name }}</strong><small>{{ $customer->submitted_at->format('d/m/Y') }} · {{ $customer->sales?->name ?? 'Đăng ký qua đại lý' }}</small></span><span>{{ $customer->status->label() }} →</span></a>@empty<p class="wc-staff-empty">Chưa có hồ sơ khách hàng.</p>@endforelse
        </section>
        <section class="wc-staff-card"><header><h2>Thi đua nội bộ</h2><p>Điểm được duyệt trong tháng</p></header>
            @forelse ($staffStandings as $person)<div class="wc-staff-standing"><span class="wc-staff-initial">{{ mb_substr($person->name, 0, 1) }}</span><strong>{{ $person->name }}</strong><b>{{ number_format($person->score) }}</b></div>@empty<p class="wc-staff-empty">Chưa có điểm hợp lệ trong tháng.</p>@endforelse
            <a href="{{ route('dealer.sales.index') }}">Xem nhân viên →</a>
        </section>
    </div>
    <div class="wc-dashboard-bottom">
        <x-competition.pending-staff :people="$pendingSales" :count="$pendingSalesCount" />
        <section class="wc-staff-card wc-qr-card"><div><h2>QR đại lý</h2><p>Mời khách hàng vào cộng đồng qua mã QR của đại lý.</p><a href="{{ route('dealers.show', $dealer->id) }}">Xem hồ sơ đại lý →</a></div><a href="{{ route('dealers.qr', $dealer->id) }}" download><img src="{{ route('dealers.qr', $dealer->id) }}" alt="Mã QR đăng ký tại {{ $dealer->name }}" width="104" height="104"><span>Tải QR</span></a></section>
    </div>
    <section class="wc-staff-card wc-staff-history"><header><h2>Thành tích đã công bố</h2></header>@forelse ($awards as $winner)<p>{{ $winner->award->period_start->format('d/m/Y') }} · Hạng {{ $winner->rank }} · {{ number_format($winner->score) }} điểm</p>@empty<p>Chưa có thành tích được công bố.</p>@endforelse<a href="{{ route('dealer.awards.index') }}">Xem lịch sử →</a></section>
</x-competition.account-layout>
