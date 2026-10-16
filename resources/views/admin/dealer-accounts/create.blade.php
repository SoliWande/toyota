<x-layout title="Tạo tài khoản đại lý">
    <div class="mx-auto max-w-xl">
        <a href="{{ route('admin.dealer-accounts.index') }}" class="text-sm text-slate-500 hover:text-red-600">← Tài khoản đại lý</a>
        <h1 class="mt-4 text-3xl font-bold">Tạo tài khoản đại lý</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600">Mỗi đại lý chỉ có một tài khoản. Tài khoản mới chờ kích hoạt; email và đại lý sẽ cố định sau khi tạo.</p>
        <div class="mt-6"><x-admin-feedback /></div>
        <form method="POST" action="{{ route('admin.dealer-accounts.store') }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
            @csrf
            <x-form-input name="name" label="Tên tài khoản" autocomplete="name" required maxlength="255" autofocus />
            <x-form-input name="email" label="Email đăng nhập" type="email" autocomplete="username" required maxlength="255" />
            <x-form-input name="phone" label="Số điện thoại (tùy chọn)" type="tel" autocomplete="tel" maxlength="30" />
            <div>
                <label for="dealer_id" class="text-sm font-medium">Đại lý</label>
                <select id="dealer_id" name="dealer_id" class="form-input" required aria-invalid="{{ $errors->has('dealer_id') ? 'true' : 'false' }}" @if ($errors->has('dealer_id')) aria-describedby="dealer_id-error" @endif>
                    <option value="">Chọn đại lý chưa có tài khoản</option>
                    @foreach ($dealers as $dealer)
                        <option value="{{ $dealer->id }}" @selected((string) old('dealer_id') === (string) $dealer->id)>{{ $dealer->name }} · {{ $dealer->code }}</option>
                    @endforeach
                </select>
                @error('dealer_id')<p id="dealer_id-error" role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                @if ($dealers->isEmpty())
                    <p class="mt-2 text-sm text-slate-600">Không có đại lý đang hoạt động chưa được cấp tài khoản.</p>
                @endif
            </div>
            <x-form-input name="password" label="Mật khẩu ban đầu (ít nhất 8 ký tự)" type="password" autocomplete="new-password" required minlength="8" maxlength="255" />
            <x-form-input name="password_confirmation" label="Xác nhận mật khẩu" type="password" autocomplete="new-password" required minlength="8" maxlength="255" />
            <button type="submit" class="button-primary w-full" @disabled($dealers->isEmpty())>Tạo tài khoản chờ kích hoạt</button>
        </form>
    </div>
</x-layout>
