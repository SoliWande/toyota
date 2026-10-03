<x-layout title="Đăng ký Sales">
    <div class="mx-auto grid max-w-4xl gap-8 md:grid-cols-2 md:gap-14">
        <div class="md:pt-8">
            <p class="mb-3 text-xs font-bold uppercase tracking-widest text-red-600">Toyota Veloz & Hilux</p>
            <h1 class="text-3xl font-bold leading-tight tracking-tight sm:text-4xl">Kết nối cộng đồng.<br>Cùng nhau tiến xa.</h1>
            <p class="mt-5 max-w-sm text-sm leading-7 text-slate-600">Tham gia chương trình dành cho nhân viên kinh doanh tại các đại lý Toyota. Mỗi kết nối là một bước đưa cộng đồng đến gần nhau hơn.</p>
            <p class="mt-5 rounded-xl border border-red-100 bg-red-50 p-4 text-sm leading-6 text-red-800">Sau khi đăng ký, tài khoản sẽ được quản trị viên xét duyệt trước khi sử dụng hệ thống.</p>
        </div>
        <div>
            <h2 class="mb-5 text-xl font-semibold">Đăng ký tài khoản Sales</h2>
            <form method="POST" action="{{ route('register') }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                @csrf
                <x-form-input name="name" label="Họ tên" autocomplete="name" maxlength="255" required autofocus />
                <x-form-input name="email" label="Email" type="email" autocomplete="username" maxlength="255" required />
                <div>
                    <label for="dealer_id" class="block text-sm font-medium">Đại lý Toyota</label>
                    <select id="dealer_id" name="dealer_id" required class="form-input @error('dealer_id') border-red-500 @enderror"
                        aria-invalid="{{ $errors->has('dealer_id') ? 'true' : 'false' }}"
                        @if ($errors->has('dealer_id')) aria-describedby="dealer_id-error" @endif>
                        <option value="">Chọn đại lý của bạn</option>
                        @foreach ($dealers as $dealer)
                            <option value="{{ $dealer->id }}" @selected((string) old('dealer_id') === (string) $dealer->id)>{{ $dealer->name }}</option>
                        @endforeach
                    </select>
                    @error('dealer_id') <p id="dealer_id-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    @if ($dealers->isEmpty())
                        <p class="mt-2 text-sm text-slate-600">Hiện chưa có đại lý nhận đăng ký. Vui lòng quay lại sau.</p>
                    @endif
                </div>
                <x-form-input name="password" label="Mật khẩu (ít nhất 8 ký tự)" type="password" autocomplete="new-password" minlength="8" maxlength="255" required />
                <x-form-input name="password_confirmation" label="Xác nhận mật khẩu" type="password" autocomplete="new-password" minlength="8" maxlength="255" required />
                @foreach (['role', 'status'] as $field)
                    @error($field) <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                @endforeach
                <button type="submit" class="button-primary w-full" @disabled($dealers->isEmpty())>Tạo tài khoản</button>
            </form>
            <p class="mt-6 text-center text-sm text-slate-600">Đã có tài khoản? <a href="{{ route('login') }}" class="font-semibold text-red-600 hover:underline">Đăng nhập</a></p>
        </div>
    </div>
</x-layout>
