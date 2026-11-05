@props(['title', 'staff' => false])
<!doctype html>
<html lang="vi"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Toyota Veloz &amp; Hilux</title>
    <link rel="stylesheet" href="{{ asset('dealer-registration/lookup-fonts.css') }}">
    @vite(['resources/css/competition.css', 'resources/css/login.css', 'resources/css/account.css', 'resources/js/competition.js', 'resources/js/account.js'])
</head><body class="competition-site wc-login-site wc-account-site {{ $staff ? 'wc-staff-site' : '' }}">
    <a class="wc-skip" href="#noi-dung">Bỏ qua điều hướng, đến nội dung</a>
    @if ($staff)<x-competition.staff-header />@else<x-competition.header :mobile-title="$title === 'Tạo tài khoản' ? $title : null" />@endif
    <main id="noi-dung" class="wc-container {{ $staff ? 'wc-staff-main' : 'wc-auth-main' }}" tabindex="-1">{{ $slot }}</main>
    @if ($staff)<x-competition.footer />@endif
</body></html>
