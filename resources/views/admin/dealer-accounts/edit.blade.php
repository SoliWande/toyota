<x-layout title="Chỉnh sửa tài khoản đại lý">
    <div class="mx-auto max-w-xl">
        <a class="text-sm text-slate-500" href="{{ route('admin.dealer-accounts.show', $account) }}">← Chi tiết tài khoản đại lý</a>
        <h1 class="mt-4 text-3xl font-bold">Chỉnh sửa tài khoản đại lý</h1>
        <x-admin-feedback />
        <form method="POST" action="{{ route('admin.dealer-accounts.update', $account) }}" class="mt-6 space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
            @csrf @method('PATCH')
            <p class="break-all text-sm">Email cố định: {{ $account->email }}</p>
            <label for="name" class="block text-sm font-medium">Họ tên</label>
            <input id="name" name="name" class="form-input" value="{{ old('name', $account->name) }}" maxlength="255" required>
            <label for="phone" class="block text-sm font-medium">Điện thoại</label>
            <input id="phone" name="phone" type="tel" class="form-input" value="{{ old('phone', $account->phone) }}" maxlength="30">
            <p class="text-sm text-slate-600">Đại lý cố định: {{ $account->dealer->name }}</p>
            <button type="submit" class="button-primary w-full">Lưu thay đổi</button>
        </form>
    </div>
</x-layout>
