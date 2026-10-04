<x-layout title="Quên mật khẩu">
    <div class="mx-auto max-w-md">
        <h1 class="text-3xl font-bold">Quên mật khẩu</h1>
        <p class="mt-3 text-sm text-slate-600">Nhập email tài khoản để nhận liên kết đặt lại mật khẩu.</p>
        <x-admin-feedback />
        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
            @csrf
            <x-form-input name="email" label="Email" type="email" required autocomplete="email" maxlength="255" />
            <button type="submit" class="button-primary w-full">Gửi liên kết</button>
        </form>
    </div>
</x-layout>
