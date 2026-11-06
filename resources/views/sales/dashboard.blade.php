<x-competition.account-layout title="Hồ sơ & khách của tôi" :staff="true" staff-page="b08">
    @if (session('success'))<p class="wc-auth-success" role="status">{{ session('success') }}</p>@endif
    <div class="wc-sales-overview">
        <div class="wc-sales-profile-column">
            <section class="wc-staff-card wc-sales-profile">
                <a class="wc-sales-avatar" href="{{ route('profile.edit') }}" aria-label="Đổi ảnh đại diện">@if ($sales->avatar_path)<img src="{{ route('sales.avatar', $sales) }}" width="64" height="64" alt="Ảnh đại diện">@else{{ mb_substr($sales->name, 0, 1) }}@endif<span aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 6l2-3h4l2 3h4v14H4V6z"/><circle cx="12" cy="12" r="4"/></svg></span></a>
                <div><h1>{{ $sales->name }}</h1><p>Nhân viên kinh doanh · {{ $sales->dealer->name }}</p><span class="wc-b08-approved">Tài khoản đã duyệt</span></div>
            </section>
            <section class="wc-sales-month-section"><h2>Thành tích tháng {{ now()->month }}</h2><div class="wc-staff-card wc-sales-month"><dl>@foreach (['approved' => 'Đã xác minh', 'pending' => 'Đang chờ', 'supplement' => 'Cần bổ sung'] as $key => $label)<div class="wc-month-{{ $key }}"><dd>{{ number_format($monthCounts[$key]) }}</dd><dt>{{ $label }}</dt></div>@endforeach</dl>
                <a class="wc-b08-rank" href="{{ route('leaderboard') }}"><span class="wc-b08-trophy" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 3h8v6a4 4 0 01-8 0zM8 5H4v2a5 5 0 004 5m8-7h4v2a5 5 0 01-4 5M12 13v6m-4 2h8"/></svg></span><span>Hạng {{ $internalRank ?? '—' }} trong {{ $sales->dealer->name }}</span><span class="wc-b08-rank-view">Xem</span><span aria-hidden="true">›</span></a>
            </div></section>
        </div>
        <section class="wc-staff-card wc-sales-invite">
            {{-- BLOCKED: the existing URL is a public Sales profile, not a Sales-attributed intake.
                 Do not publish the unsupported desktop promise about attribution/points. --}}
            @if ($inviteQr)<div class="wc-b08-qr-panel"><div class="wc-sales-qr"><img src="{{ $inviteQr }}" width="200" height="200" alt="QR hồ sơ Sales {{ $sales->name }}"></div><button class="wc-b08-mobile-link" type="button" data-copy-text="{{ $inviteUrl }}">{{ $inviteUrl }}</button></div>@endif
            <div><h2>Link mời của bạn</h2><p>Đưa khách quét mã trên điện thoại của bạn, hoặc gửi link qua Zalo.</p>
                @if ($inviteUrl)<div class="wc-share-link"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 13a5 5 0 007 0l3-3a5 5 0 00-7-7l-2 2m3 6a5 5 0 00-7 0l-3 3a5 5 0 007 7l2-2"/></svg><span class="wc-b08-url">{{ $inviteUrl }}</span><button type="button" data-copy-text="{{ $inviteUrl }}"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V4H4v12h4"/></svg>Sao chép</button></div>@endif
                <div class="wc-share-actions">@if ($inviteUrl)<button class="wc-staff-button wc-b08-zalo" type="button" data-zalo-text="{{ $inviteUrl }}"><svg class="wc-b08-desktop-chat" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 11a9 9 0 01-9 9H4l-1 1v-9a9 9 0 1118-1z"/></svg>Gửi qua Zalo</button><button class="wc-b08-copy-mobile" type="button" data-copy-text="{{ $inviteUrl }}"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V4H4v12h4"/></svg>Sao chép link</button>@endif<a class="wc-b08-download" href="{{ route('dealers.qr', [$sales->dealer_id, 'download' => 1]) }}"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 3v12m-4-4l4 4 4-4M4 19h16"/></svg><span class="wc-b08-download-desktop">Tải QR đại lý để in standee</span><span class="wc-b08-download-mobile">Tải QR standee</span></a></div>
            </div>
        </section>
    </div>
    <x-competition.customer-list :customers="$customers" :filters="$filters" :active-tab="$activeTab" :tab-counts="$tabCounts" :presentation="$customerPresentation" />
    <p class="wc-share-status" data-share-status role="status" aria-live="polite"></p>
</x-competition.account-layout>
