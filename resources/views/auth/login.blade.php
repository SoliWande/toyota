<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Đăng nhập · Toyota Veloz &amp; Hilux</title>
    <link rel="stylesheet" href="{{ asset('dealer-registration/lookup-fonts.css') }}">
    @vite(['resources/css/competition.css', 'resources/css/login.css', 'resources/js/competition.js', 'resources/js/login.js'])
</head>
<body class="competition-site wc-login-site">
    <a class="wc-skip" href="#noi-dung">Bỏ qua điều hướng, đến nội dung</a>
    <x-competition.header />
    <main id="noi-dung" class="wc-container wc-auth-main" tabindex="-1">
        <aside class="wc-auth-art" aria-label="Kết nối cộng đồng Toyota">
            <img class="wc-auth-scene" src="{{ asset('competition/login/scene.png') }}" width="780" height="1560" alt="">
            <div class="wc-auth-art-copy">
                <p class="wc-auth-eyebrow">DÀNH CHO NHÂN VIÊN ĐẠI LÝ</p>
                <h2>Mỗi kết nối.<br>Một hành trình mới.</h2>
                <p>Mời khách vào cộng đồng, theo dõi thứ hạng của đại lý.</p>
            </div>
        </aside>
        <section class="wc-auth-panel" aria-labelledby="wc-auth-title">
            <header class="wc-auth-heading"><h1 id="wc-auth-title">Đăng nhập</h1><p>Dành cho nhân viên và quản lý các đại lý Toyota.</p></header>
            <form class="wc-auth-form" method="POST" action="{{ route('login') }}" data-login-form>
                @csrf
                @if (session('success'))<p class="wc-auth-success" role="status">{{ session('success') }}</p>@endif
                @php($otherErrors = collect($errors->getMessages())->except(['email', 'password'])->flatten())
                @if ($otherErrors->isNotEmpty())<div class="wc-auth-error" role="alert">@foreach ($otherErrors as $message)<p>{{ $message }}</p>@endforeach</div>@endif
                <div class="wc-auth-fields">
                    <div class="wc-auth-field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ is_string(old('email')) ? old('email') : '' }}" autocomplete="username" maxlength="255" placeholder="Nhập email" required autofocus aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @if ($errors->has('email')) aria-describedby="email-error" @endif>
                        @error('email')<p id="email-error" class="wc-auth-field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="wc-auth-field">
                        <label for="password">Mật khẩu</label>
                        <div class="wc-auth-password">
                            <input id="password" name="password" type="password" autocomplete="current-password" maxlength="255" placeholder="Nhập mật khẩu" required aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @if ($errors->has('password')) aria-describedby="password-error" @endif>
                            <button class="wc-auth-toggle" type="button" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Hiện mật khẩu" hidden>Hiện</button>
                        </div>
                        @error('password')<p id="password-error" class="wc-auth-field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="wc-auth-actions">
                    <a class="wc-auth-forgot" href="{{ route('password.request') }}">Quên mật khẩu?</a>
                    <p class="wc-auth-register">Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký</a></p>
                    <button class="wc-button wc-auth-submit" type="submit" data-login-submit>Đăng nhập</button>
                </div>
            </form>
            <div class="wc-auth-divider" aria-hidden="true"></div>
            <aside class="wc-auth-owner">
                <span class="wc-auth-owner-icon"><img class="wc-auth-icon-desktop" src="{{ asset('competition/login/car-22.svg') }}" width="22" height="22" alt=""><img class="wc-auth-icon-mobile" src="{{ asset('competition/login/car-18.svg') }}" width="18" height="18" alt=""></span>
                <div><h2>Bạn là chủ xe muốn vào cộng đồng?</h2><p>Không cần tài khoản, chỉ cần quét mã QR tại đại lý.</p></div>
            </aside>
        </section>
    </main>
</body>
</html>
