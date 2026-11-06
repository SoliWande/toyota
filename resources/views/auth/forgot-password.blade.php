<x-competition.account-layout title="Quên mật khẩu" screen="password" mobile-title="Quên mật khẩu">
    <x-competition.account-art :recovery="true" />
    <section class="wc-auth-panel wc-password-panel" aria-labelledby="wc-auth-title">
        <a class="wc-password-back" href="{{ route('login') }}"><span aria-hidden="true">‹</span>Quay lại đăng nhập</a>
        <header class="wc-auth-heading"><h1 id="wc-auth-title">Đặt lại mật khẩu</h1><p>Nhập email công việc để yêu cầu liên kết đặt lại mật khẩu.</p></header>
        @if (session('success'))<p @class(['wc-auth-success' => !session('password_reset_unavailable'), 'wc-mail-notice' => session('password_reset_unavailable')]) role="status">{{ session('success') }}</p>@endif
        <form method="POST" action="{{ route('password.email') }}" class="wc-auth-form wc-password-request" data-account-form>
            @csrf
            <x-competition.account-input name="email" label="Email công việc" type="email" required autocomplete="email" maxlength="255" placeholder="Nhập email công việc" />
            <button type="submit" class="wc-button wc-auth-submit" data-account-submit>Gửi link đặt lại</button>
        </form>
        <a class="wc-password-mobile-back" href="{{ route('login') }}">Quay lại đăng nhập</a>
    </section>
</x-competition.account-layout>
