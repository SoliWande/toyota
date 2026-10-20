<x-layout :title="'Tài khoản Dealer · '.$dealer->name">
    <div class="mx-auto max-w-xl">
        <a href="{{ route('admin.dealers.show', $dealer) }}" class="text-sm text-slate-500 hover:text-red-700">← {{ $dealer->name }}</a>
        <h1 class="my-6 text-3xl font-bold">Tài khoản quản lý đại lý</h1>
        <x-admin-feedback />
        <form method="POST" action="{{ route($manager ? 'admin.dealers.account.update' : 'admin.dealers.account.store', $dealer) }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
            @csrf
            @if ($manager) @method('PATCH') @endif
            <label class="block text-sm font-medium" for="name">Họ tên người quản lý</label>
            <input id="name" name="name" value="{{ old('name', $manager?->name) }}" class="form-input" required maxlength="255" autocomplete="name">
            @if ($manager)
                <p class="break-all text-sm">Email cố định: {{ $manager->email }}</p>
                <p class="text-sm text-slate-500">Người quản lý có thể đổi mật khẩu trong hồ sơ hoặc dùng chức năng quên mật khẩu.</p>
            @else
                <x-form-input name="email" label="Email tài khoản Dealer" type="email" required autocomplete="email" maxlength="255" />
                <x-form-input name="password" label="Mật khẩu riêng cho tài khoản (ít nhất 12 ký tự)" type="password" required autocomplete="new-password" minlength="12" maxlength="255" />
                <x-form-input name="password_confirmation" label="Xác nhận mật khẩu" type="password" required autocomplete="new-password" minlength="12" maxlength="255" />
            @endif
            <label class="block text-sm font-medium" for="status">Trạng thái tài khoản</label>
            <select id="status" name="status" class="form-input"><option value="active" @selected(old('status', $manager?->status?->value ?? 'active') === 'active')>Hoạt động</option><option value="blocked" @selected(old('status', $manager?->status?->value) === 'blocked')>Đã khóa</option></select>
            <button class="button-primary" type="submit">{{ $manager ? 'Lưu tài khoản' : 'Tạo tài khoản Dealer' }}</button>
        </form>
    </div>
</x-layout>
