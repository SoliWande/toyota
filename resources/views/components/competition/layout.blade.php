@props(['title' => 'Thi đua cộng đồng Toyota Veloz & Hilux', 'stickyFooter' => false, 'dealerProfile' => false])
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Cùng các đại lý Toyota kết nối chủ xe với cộng đồng Veloz & Hilux. Lan toả giá trị, ghi nhận nỗ lực.">
    <title>{{ $title }}</title>
    <link rel="stylesheet" href="{{ asset('dealer-registration/lookup-fonts.css') }}">
    @vite(['resources/css/competition.css', 'resources/js/competition.js'])
</head>
<body @class(['competition-site', 'wc-sticky-footer' => $stickyFooter, 'wc-d-profile-site' => $dealerProfile])>
    <a class="wc-skip" href="#noi-dung">Bỏ qua điều hướng, đến nội dung</a>
    <x-competition.header :dealer-profile="$dealerProfile" />
    <main id="noi-dung" tabindex="-1">{{ $slot }}</main>
    <x-competition.footer />
</body>
</html>
