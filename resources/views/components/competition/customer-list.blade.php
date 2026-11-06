@props(['customers', 'filters', 'activeTab', 'tabCounts', 'presentation'])
<section class="wc-customers" aria-labelledby="wc-customers-title">
    <header class="wc-customers-heading">
        <h2 id="wc-customers-title">Khách của tôi</h2>
        <nav class="wc-b08-pills" aria-label="Khách của tôi">
            @foreach (['remind' => 'Cần nhắc', 'verification' => 'Chờ xác minh', 'verified' => 'Đã xác minh', 'all' => 'Tất cả'] as $key => $label)
                <a href="{{ route('sales.dashboard', ['tab' => $key]) }}" @if ($activeTab === $key) aria-current="page" @endif>{{ $label }} <span>{{ number_format($tabCounts[$key]) }}</span></a>
            @endforeach
        </nav>
        <a class="wc-staff-button wc-staff-outline" href="{{ route('sales.submissions.create') }}"><span aria-hidden="true">＋</span> Khai báo tay</a>
    </header>
    <div class="wc-customer-table">
        <div class="wc-customer-table-heading"><span>KHÁCH</span><span>NGUỒN</span><span>THỜI GIAN</span><span>TRẠNG THÁI</span><span>GHI CHÚ</span><span></span></div>
        @forelse ($customers as $customer)
            @php
                $row = $presentation[$customer->id];
                $phone = preg_replace('/\D/', '', $customer->phone ?? '');
                $maskedPhone = strlen($phone) >= 7 ? substr($phone, 0, 2).str_repeat('*', strlen($phone) - 5).substr($phone, -3) : '';
                $source = $customer->source === 'sales' ? 'Khai báo tay' : null;
                $age = $customer->submitted_at?->locale('vi')->diffForHumans(now(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 1]);
                $lookup = $row['stage'] === 'supplement' ? app(\App\Services\StaffCustomerLinks::class)->lookup($customer) : null;
            @endphp
            <article class="wc-customer-table-row">
                <a href="{{ route('sales.submissions.show', $customer) }}" class="wc-staff-person"><span class="wc-staff-initial">{{ mb_substr($customer->customer_name, 0, 1) }}</span><span><strong>{{ $customer->customer_name }}</strong><small>{{ $maskedPhone }}</small><small class="wc-b08-mobile-meta">{{ $maskedPhone }} · {{ $source }} · {{ $age }}</small></span></a>
                <span class="wc-b08-source">{{ $source }}</span><time datetime="{{ $customer->submitted_at?->toIso8601String() }}">{{ $age }}</time>
                <span class="wc-customer-badge wc-b08-state-{{ $row['stage'] }}">{{ $row['label'] }}</span>
                <p class="wc-b08-note">{{ $row['note'] }}</p>
                <div class="wc-b08-actions">@if ($lookup)<a class="wc-b08-lookup" href="{{ route('sales.submissions.saved', $customer) }}">Gửi link tra cứu</a>@endif</div>
            </article>
        @empty<p class="wc-staff-empty">{{ filled($filters['q'] ?? null) || filled($filters['status'] ?? null) ? 'Không có khai báo phù hợp.' : 'Bạn chưa có khai báo nào' }}</p>@endforelse
    </div>
    @if ($customers->hasPages())<nav class="wc-staff-page-links" aria-label="Phân trang khách hàng"><span>Trang {{ $customers->currentPage() }}/{{ $customers->lastPage() }} · {{ $customers->total() }} hồ sơ</span>@if (!$customers->onFirstPage())<a href="{{ $customers->previousPageUrl() }}" rel="prev">‹ Trước</a>@endif @if ($customers->hasMorePages())<a href="{{ $customers->nextPageUrl() }}" rel="next">Sau ›</a>@endif</nav>@endif
</section>
