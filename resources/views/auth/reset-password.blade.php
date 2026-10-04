<x-layout title="Đặt lại mật khẩu">
    <div class="mx-auto max-w-md">
        <h1 class="text-3xl font-bold">Đặt lại mật khẩu</h1>
        <x-admin-feedback />
        <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">
            <p class="break-all text-sm">Email tài khoản: {{ $email }}</p>
            <x-form-input name="password" label="Mật khẩu mới" type="password" required autocomplete="new-password" minlength="8" maxlength="255" />
            <x-form-input name="password_confirmation" label="Xác nhận mật khẩu" type="password" required autocomplete="new-password" maxlength="255" />
            <button type="submit" class="button-primary w-full">Đặt lại mật khẩu</button>
        </form>
    </div>
</x-layout>
