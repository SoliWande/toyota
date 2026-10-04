<x-layout title="Đăng nhập">
    <div class="mx-auto max-w-md">
        <p class="mb-3 text-xs font-bold uppercase tracking-widest text-red-600">Kết nối để cùng tiến xa</p>
        <h1 class="text-3xl font-bold tracking-tight">Chào mừng trở lại</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600">Đăng nhập để theo dõi tài khoản và chương trình cộng đồng Toyota.</p>
        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @csrf
            <x-form-input name="email" label="Email" type="email" autocomplete="username" maxlength="255" required autofocus />
            <x-form-input name="password" label="Mật khẩu" type="password" autocomplete="current-password" maxlength="255" required />
            <button type="submit" class="button-primary w-full">Đăng nhập</button>
        </form>
        <x-admin-feedback />
        <p class="mt-4 text-center text-sm"><a class="text-red-600 hover:underline" href="{{ route('password.request') }}">Quên mật khẩu?</a></p>
        <p class="mt-6 text-center text-sm text-slate-600">Chưa có tài khoản? <a href="{{ route('register') }}" class="font-semibold text-red-600 hover:underline">Đăng ký Sales</a></p>
    </div>
</x-layout>
