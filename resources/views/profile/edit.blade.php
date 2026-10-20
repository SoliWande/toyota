<x-layout title="Hồ sơ cá nhân">
    <div class="mx-auto max-w-xl space-y-6">
        <h1 class="text-3xl font-bold">Hồ sơ cá nhân</h1>
        <x-admin-feedback />
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
            @csrf @method('PATCH')
            <div><p class="text-sm font-medium">Email</p><p class="mt-1 break-all">{{ $user->email }}</p><p class="mt-2 text-sm text-slate-500">Email cố định. Để sử dụng email khác, vui lòng đăng ký tài khoản mới.</p></div>
            <label class="block text-sm font-medium" for="name">Họ tên</label>
            <input id="name" name="name" class="form-input" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name">
            @if ($user->role === \App\Enums\UserRole::Sales)
                <div class="flex flex-wrap items-center gap-4">
                    <x-sales-avatar :sales-id="$user->id" :has-avatar="(bool) $user->avatar_path" />
                    <div class="min-w-0 flex-1"><label for="avatar" class="block text-sm font-medium">Ảnh đại diện</label><input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm"><p class="mt-2 text-xs text-slate-500">JPG, PNG hoặc WebP, tối đa 5 MB và 4096 × 4096 px. Avatar và tên hiển thị sẽ xuất hiện trên profile public; số điện thoại được che bớt.</p></div>
                </div>
                <label class="block text-sm font-medium" for="phone">Điện thoại</label>
                <input id="phone" name="phone" type="tel" class="form-input" value="{{ old('phone', $user->phone) }}" maxlength="30" autocomplete="tel">
                <p class="text-sm text-slate-600">Đại lý hiện tại: {{ $user->dealer->name }}</p>
                @if ($user->public_slug)<a href="{{ route('sales.public.show', ['slug' => $user->public_slug]) }}" class="inline-flex py-3 font-semibold text-red-700 hover:underline" target="_blank" rel="noopener noreferrer">Xem profile public của tôi →</a>@endif
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
