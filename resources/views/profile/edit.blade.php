<x-layout title="Hồ sơ cá nhân">
    <div class="mx-auto max-w-xl space-y-6">
        <h1 class="text-3xl font-bold">Hồ sơ cá nhân</h1>
        <x-admin-feedback />
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
            @csrf @method('PATCH')
            <div><p class="text-sm font-medium">Email</p><p class="mt-1 break-all">{{ $user->email }}</p><p class="mt-2 text-sm text-slate-500">Email cố định. Để sử dụng email khác, vui lòng đăng ký tài khoản mới.</p></div>
            <label class="block text-sm font-medium" for="name">Họ tên</label>
            <input id="name" name="name" class="form-input" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name">
            @if ($user->role === \App\Enums\UserRole::Sales)
                <label class="block text-sm font-medium" for="phone">Điện thoại</label>
                <input id="phone" name="phone" type="tel" class="form-input" value="{{ old('phone', $user->phone) }}" maxlength="30" autocomplete="tel">
                <p class="text-sm text-slate-600">Đại lý hiện tại: {{ $user->dealer->name }}</p>
            @endif
            <button class="button-primary w-full" type="submit">Lưu hồ sơ</button>
        </form>
        <form method="POST" action="{{ route('profile.password') }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
            @csrf @method('PUT')
            <h2 class="text-xl font-semibold">Đổi mật khẩu</h2>
            <x-form-input name="current_password" label="Mật khẩu hiện tại" type="password" required autocomplete="current-password" maxlength="255" />
            <x-form-input name="password" label="Mật khẩu mới" type="password" required autocomplete="new-password" minlength="8" maxlength="255" />
            <x-form-input name="password_confirmation" label="Xác nhận mật khẩu mới" type="password" required autocomplete="new-password" maxlength="255" />
            <button class="button-primary w-full" type="submit">Đổi mật khẩu</button>
        </form>
    </div>
</x-layout>
