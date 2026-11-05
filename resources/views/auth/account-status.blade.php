@php
    $rejected = $account->status === \App\Enums\UserStatus::Rejected;
    $blocked = $account->status === \App\Enums\UserStatus::Blocked;
    $manager = $account->role === \App\Enums\UserRole::Dealer;
    $approver = $manager ? 'Admin Ô tô Sài Gòn' : ($account->role === \App\Enums\UserRole::Admin ? 'Quản trị hệ thống' : 'Quản lý đại lý');
    $roleLabel = $account->role === \App\Enums\UserRole::Admin ? 'Quản trị viên' : ($manager ? 'Quản lý đại lý' : 'Nhân viên kinh doanh');
@endphp
<x-competition.account-layout title="Trạng thái tài khoản">
    <x-competition.account-art :status="$account->status->value" />
    <section class="wc-auth-panel wc-status-panel" aria-labelledby="wc-auth-title">
        @if (session('success'))<p class="wc-auth-success" role="status">{{ session('success') }}</p>@endif
        @if ($errors->any())<div class="wc-auth-error" role="alert">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
        <div class="wc-status-hero">
        @if ($rejected || $blocked)<span class="wc-status-warning"><img src="{{ asset('competition/account/rejected.svg') }}" width="40" height="40" alt=""></span>@else<img class="wc-status-illustration" src="{{ asset('competition/account/pending.svg') }}" width="120" height="120" alt="">@endif
        <header class="wc-auth-heading"><h1 id="wc-auth-title">{{ $blocked ? 'Tài khoản đã bị khóa' : ($rejected ? 'Chưa duyệt được tài khoản' : 'Tài khoản đang chờ duyệt') }}</h1>
            <p>Xin chào {{ $account->name }}. {{ $rejected ? 'Đăng ký chưa được chấp nhận.' : ($blocked ? 'Vui lòng liên hệ cấp quản lý để được hỗ trợ.' : 'Thông tin của bạn đã được gửi đến '.$approver.' để xét duyệt.') }}</p>
        </header>
        </div>
        <div class="wc-status-dealer">{{ $account->dealer?->name ?? 'Chưa có đại lý' }}</div>
        <dl class="wc-status-meta">
            <div><dt>{{ $rejected ? 'Cấp xét duyệt' : 'Người duyệt' }}</dt><dd>{{ $approver }}</dd></div>
            <div><dt>{{ $rejected || $blocked ? 'Thời điểm xét duyệt' : 'Ngày gửi' }}</dt><dd>{{ \Carbon\Carbon::parse(($rejected || $blocked ? $account->reviewed_at : $sentAt) ?? $account->created_at)->format('d/m/Y H:i') }}</dd></div>
            <div><dt>Vai trò</dt><dd>{{ $roleLabel }}</dd></div>
            @if ($rejected)<div class="wc-status-reason"><dt>Lý do</dt><dd>{{ $reasonPublic && $account->rejection_reason ? $account->rejection_reason : 'Vui lòng liên hệ cấp xét duyệt để kiểm tra thông tin.' }}</dd></div>@endif
        </dl>
        <aside class="wc-status-help"><h2>{{ $rejected ? 'Nếu có nhầm lẫn' : 'Trong lúc chờ' }}</h2><p>{{ $rejected ? 'Liên hệ cấp xét duyệt để xác nhận thông tin trước khi gửi lại. Tài khoản và đại lý của bạn được giữ nguyên.' : 'Trạng thái được cập nhật tại đây sau khi xét duyệt. Bạn có thể xem trước thể lệ chương trình.' }}</p><a href="{{ route('campaign.rules') }}">Xem thể lệ chương trình →</a></aside>
        @if ($rejected && $account->dealer?->is_active)<form method="POST" action="{{ route('account.resubmit') }}" data-account-form>@csrf<button class="wc-button wc-auth-submit" data-account-submit>Gửi đăng ký lại</button></form>@endif
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="wc-status-logout">Đăng xuất</button></form>
    </section>
</x-competition.account-layout>
