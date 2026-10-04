@props(['title' => 'Cộng đồng Toyota'])
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Toyota Veloz & Hilux</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <nav aria-label="Điều hướng chính" class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-5 sm:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <span class="h-8 w-1 rounded-full bg-red-600" aria-hidden="true"></span>
                <span class="text-sm font-bold tracking-wide">TOYOTA <span class="block text-xs font-normal tracking-normal text-slate-500">Veloz & Hilux · Kết nối cộng đồng</span></span>
            </a>
            <a href="{{ route('leaderboard') }}" class="py-2 text-sm font-medium hover:text-red-600">Bảng xếp hạng</a>
            <a href="{{ route('awards.index') }}" class="py-2 text-sm font-medium hover:text-red-600">Vinh danh</a>
            @auth
                @if (auth()->user()->status === \App\Enums\UserStatus::Active)
                    <a href="{{ route('profile.edit') }}" class="py-2 text-sm font-medium hover:text-red-600">Hồ sơ cá nhân</a>
                @endif
                <div class="flex items-center gap-4 text-sm">
                    <a href="{{ route(auth()->user()->homeRoute()) }}" class="font-medium hover:text-red-600">Tài khoản</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg px-2 py-2 text-slate-600 hover:text-red-600 focus-visible:outline-red-600">Đăng xuất</button>
                    </form>
                </div>
            @else
                <a href="{{ route('login') }}" class="py-2 text-sm font-medium hover:text-red-600">Đăng nhập</a>
            @endauth
        </nav>
    </header>
    <main class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-16">
        {{ $slot }}
    </main>
    <footer class="px-5 pb-8 text-center text-xs text-slate-500">Cùng kết nối cộng đồng Toyota Veloz & Hilux.</footer>
</body>
</html>
