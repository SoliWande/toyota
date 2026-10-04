@props(['title' => 'Kết nối cộng đồng Toyota Veloz & Hilux'])
@php($joinUrl = auth()->check() ? route(auth()->user()->homeRoute()) : route('register'))
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Cùng Sales và đại lý Toyota kết nối thành viên cộng đồng Veloz & Hilux. Theo dõi thành tích, bảng xếp hạng và những kỳ vinh danh.">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-site bg-[#faf9f6] text-stone-900 antialiased">
    <a href="#noi-dung" class="sr-only fixed left-4 top-4 z-50 rounded-lg bg-stone-900 px-5 py-3 text-white focus:not-sr-only">Bỏ qua điều hướng, đến nội dung</a>
    <header class="relative z-20 border-b border-stone-200/70 bg-[#faf9f6]">
        <div class="public-container flex min-h-20 items-center justify-between gap-5">
            <x-public.brand />
            <nav aria-label="Điều hướng chính" class="hidden items-center gap-6 lg:flex"><x-public.navigation /></nav>
            <div class="hidden items-center gap-5 lg:flex">
                <a href="{{ auth()->check() ? route(auth()->user()->homeRoute()) : route('login') }}" class="py-3 text-sm font-semibold">{{ auth()->check() ? 'Tài khoản' : 'Đăng nhập' }}</a>
                <a href="{{ $joinUrl }}" class="public-button">Tham gia <x-public.arrow /></a>
            </div>
            <details class="public-menu group lg:hidden">
                <summary class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-full border border-stone-300" aria-label="Mở menu điều hướng">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 8h16M4 16h16" /></svg>
                </summary>
                <nav aria-label="Điều hướng mobile" class="absolute inset-x-0 top-full border-b border-stone-200 bg-[#faf9f6] px-6 pb-6 shadow-lg">
                    <x-public.navigation :mobile="true" />
                    <div class="mt-5 flex items-center justify-between gap-4">
                        <a href="{{ auth()->check() ? route(auth()->user()->homeRoute()) : route('login') }}" class="py-3 text-sm font-semibold">{{ auth()->check() ? 'Tài khoản' : 'Đăng nhập' }}</a>
                        <a href="{{ $joinUrl }}" class="public-button">Tham gia <x-public.arrow /></a>
                    </div>
                </nav>
            </details>
        </div>
    </header>
    <main id="noi-dung" tabindex="-1">{{ $slot }}</main>
    <footer class="border-t border-stone-200 py-10">
        <div class="public-container flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
            <x-public.brand />
            <div class="text-xs leading-6 text-stone-500"><p>Cùng kết nối. Cùng tạo nên giá trị.</p><p>Cộng đồng Toyota Veloz & Hilux.</p></div>
        </div>
    </footer>
</body>
</html>
