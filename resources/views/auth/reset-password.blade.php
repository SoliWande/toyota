<x-competition.account-layout title="Mật khẩu mới" screen="password" mobile-title="Mật khẩu mới">
    <x-competition.account-art :recovery="true" />
    <section class="wc-auth-panel wc-password-panel" aria-labelledby="wc-auth-title">
        <a class="wc-password-back" href="{{ route('login') }}"><span aria-hidden="true">‹</span>Quay lại đăng nhập</a>
        <header class="wc-auth-heading"><h1 id="wc-auth-title">Tạo mật khẩu mới</h1><p>Đặt mật khẩu mới cho tài khoản của bạn.</p></header>
        <form method="POST" action="{{ route('password.store') }}" class="wc-auth-form wc-password-reset" data-account-form>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="email" value="{{ $email }}">
            <p class="wc-password-email">Email tài khoản: {{ $email }}</p>
            @if ($errors->has('email') || $errors->has('token'))<div class="wc-auth-error" role="alert">@foreach (array_merge($errors->get('email'), $errors->get('token')) as $message)<p>{{ $message }}</p>@endforeach<a href="{{ route('password.request') }}">Yêu cầu liên kết mới</a></div>@endif
            <x-competition.account-input name="password" label="Mật khẩu mới" type="password" required autocomplete="new-password" minlength="8" maxlength="255" />
            <x-competition.account-input name="password_confirmation" label="Xác nhận mật khẩu" type="password" required autocomplete="new-password" maxlength="255" />
            <button type="submit" class="wc-button wc-auth-submit" data-account-submit>Đặt lại mật khẩu</button>
        </form>
        <a class="wc-password-mobile-back" href="{{ route('login') }}">Quay lại đăng nhập</a>
    </section>
</x-competition.account-layout>
