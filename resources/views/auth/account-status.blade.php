@php
    $rejected = $account->status === \App\Enums\UserStatus::Rejected;
    $blocked = $account->status === \App\Enums\UserStatus::Blocked;
    $manager = $account->role === \App\Enums\UserRole::Dealer;
    $approver = $manager ? 'Admin Ô tô Sài Gòn' : ($account->role === \App\Enums\UserRole::Admin ? 'Quản trị hệ thống' : 'Quản lý đại lý');
    $roleLabel = $account->role === \App\Enums\UserRole::Admin ? 'Quản trị viên' : ($manager ? 'Quản lý đại lý' : 'Nhân viên kinh doanh');
@endphp
<x-competition.account-layout title="Trạng thái tài khoản" screen="status">
    <x-competition.account-art :status="$account->status->value" />
    <section class="wc-auth-panel wc-status-panel" aria-labelledby="wc-auth-title">
        @if (session('success'))<p class="wc-auth-success" role="status">{{ session('success') }}</p>@endif
        @if ($errors->any())<div class="wc-auth-error" role="alert">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
        <div class="wc-status-hero">
            @if ($rejected || $blocked)<span class="wc-status-warning"><img src="{{ asset('competition/account/rejected.svg') }}" width="40" height="40" alt=""></span>@else<img class="wc-status-illustration" src="{{ asset('competition/account/pending.svg') }}" width="96" height="96" alt="">@endif
            <header class="wc-auth-heading"><h1 id="wc-auth-title">{{ $blocked ? 'Tài khoản đã bị khóa' : ($rejected ? 'Chưa duyệt được tài khoản' : 'Tài khoản đang chờ duyệt') }}</h1>
                <p>@if ($blocked)Vui lòng liên hệ {{ $approver }} để được hỗ trợ.@elseif ($rejected){{ $approver }}{{ !$manager && $account->dealer ? ' của '.$account->dealer->name : '' }} đã từ chối đăng ký này. Bạn xem lý do bên dưới.@else{{ $approver }}{{ !$manager && $account->dealer ? ' của '.$account->dealer->name : '' }} sẽ xét duyệt cho bạn. Trạng thái được cập nhật tại đây.@endif</p>
            </header>
        </div>
        <dl class="wc-status-meta">
            @if ($rejected)<div class="wc-status-reason"><dt>Lý do</dt><dd>{{ $reasonPublic && $account->rejection_reason ? $account->rejection_reason : 'Vui lòng liên hệ cấp xét duyệt để kiểm tra thông tin.' }}</dd></div>@endif
            <div><dt>Người duyệt</dt><dd>{{ !$manager && $account->dealer ? 'Quản lý '.$account->dealer->name : $approver }}</dd></div>
            <div><dt>{{ $rejected || $blocked ? 'Thời điểm' : 'Đã gửi' }}</dt><dd>@if ($rejected || $blocked){{ $account->reviewed_at?->format('d/m/Y H:i') ?? 'Chưa có thời điểm ghi nhận' }}@else{{ \Carbon\Carbon::parse($sentAt)->format('d/m/Y H:i') }}@endif</dd></div>
            @if (!$rejected)<div><dt>Vai trò</dt><dd>{{ $roleLabel }}</dd></div>@endif
        </dl>
        @if ($manager)<p class="wc-status-context">{{ $account->dealer?->name ?? 'Chưa có đại lý' }} · {{ $roleLabel }}</p>@endif
        <aside class="wc-status-help">
            <span class="wc-status-help-icon" aria-hidden="true">@if ($rejected || $blocked)<img src="{{ asset('competition/leaderboard/info.svg') }}" width="20" height="20" alt="">@else<img src="{{ asset('competition/nav-rules.png') }}" width="20" height="20" alt="">@endif</span>
            <div><h2>{{ $blocked ? 'Liên hệ hỗ trợ' : ($rejected ? 'Nếu có nhầm lẫn' : 'Trong lúc chờ') }}</h2><p>{{ $blocked ? 'Tài khoản bị khóa không thể gửi đăng ký lại. Vui lòng liên hệ cấp quản lý.' : ($rejected ? 'Liên hệ cấp xét duyệt để kiểm tra lại, rồi gửi lại hồ sơ. Tài khoản, email và đại lý của bạn được giữ nguyên.' : 'Đọc thể lệ để biết cách tính điểm, và cách mời khách sao cho được xác minh nhanh.') }}</p></div>
            @if ($rejected && $account->dealer?->is_active)<form method="POST" action="{{ route('account.resubmit') }}" data-account-form>@csrf<button class="wc-recovery-secondary" data-account-submit>Đăng ký lại</button></form>@else<a class="wc-recovery-secondary" href="{{ route('campaign.rules') }}">Xem thể lệ</a>@endif
        </aside>
        @if ($rejected)<a class="wc-status-rules" href="{{ route('campaign.rules') }}">Xem thể lệ chương trình →</a>@endif
        <form method="POST" action="{{ route('logout') }}" class="wc-status-logout-form">@csrf<button class="wc-status-logout"><span aria-hidden="true">↪</span>Đăng xuất</button></form>
    </section>
</x-competition.account-layout>
