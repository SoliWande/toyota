<x-competition.account-layout title="Tạo tài khoản">
    @php($selectedDealer = is_string(old('dealer_id')) || is_int(old('dealer_id')) ? (string) old('dealer_id') : '')
    <x-competition.account-art />
    <section class="wc-auth-panel wc-register-panel" aria-labelledby="wc-auth-title">
        <header class="wc-auth-heading"><h1 id="wc-auth-title">Tạo tài khoản</h1><p>Chọn vai trò và đại lý bạn đang làm việc.</p></header>
        <form class="wc-auth-form wc-register-form" method="POST" action="{{ route('register') }}" data-account-form>
            @csrf
            <fieldset class="wc-role-field"><legend>Bạn là</legend><div class="wc-role-options">
                @foreach (['sales' => ['Nhân viên kinh doanh', 'Quản lý đại lý duyệt'], 'dealer' => ['Quản lý đại lý', 'Ô tô Sài Gòn duyệt']] as $value => [$label, $help])
                    <label class="wc-role-card"><input type="radio" name="role" value="{{ $value }}" @checked(old('role', 'sales') === $value) required><span class="wc-role-symbol wc-role-symbol-{{ $value }}" aria-hidden="true"></span><span><strong>{{ $label }}</strong><small>{{ $help }}</small></span><span class="wc-role-check" aria-hidden="true">✓</span></label>
                @endforeach
            </div>@error('role')<p class="wc-auth-field-error" role="alert">{{ $message }}</p>@enderror</fieldset>
            <div class="wc-register-fields">
                <div class="wc-auth-field"><label for="dealer_id">Đại lý Toyota</label>
                    <input type="search" data-dealer-search aria-label="Tìm đại lý trong danh sách" placeholder="Tìm trong {{ $dealers->count() }} đại lý" autocomplete="off" hidden>
                    <select id="dealer_id" name="dealer_id" required aria-invalid="{{ $errors->has('dealer_id') ? 'true' : 'false' }}" @error('dealer_id') aria-describedby="dealer_id-error" @enderror>
                        <option value="">Chọn đại lý của bạn</option>
                        @foreach ($dealers as $dealer)<option value="{{ $dealer->id }}" data-has-manager="{{ $dealer->manager_exists ? '1' : '0' }}" @selected($selectedDealer === (string) $dealer->id)>{{ $dealer->name }}</option>@endforeach
                    </select>
                    <small data-dealer-hint>Nhân viên do quản lý đại lý duyệt. Quản lý do Admin duyệt.</small>
                    @error('dealer_id')<p id="dealer_id-error" class="wc-auth-field-error" role="alert">{{ $message }}</p>@enderror
                    @if ($dealers->isEmpty())<p>Hiện chưa có đại lý nhận đăng ký. Vui lòng quay lại sau.</p>@endif
                </div>
                <div class="wc-auth-fields">
                    <x-competition.account-input name="name" label="Họ và tên" autocomplete="name" maxlength="255" placeholder="Nhập họ và tên" required />
                    <x-competition.account-input name="phone" label="Số điện thoại" type="tel" autocomplete="tel" maxlength="30" placeholder="Nhập số điện thoại" required />
                    <x-competition.account-input name="email" label="Email công việc" type="email" autocomplete="username" maxlength="255" placeholder="Nhập email" required />
                    <x-competition.account-input name="password" label="Mật khẩu" type="password" autocomplete="new-password" minlength="8" maxlength="255" placeholder="Ít nhất 8 ký tự" required />
                    <x-competition.account-input name="password_confirmation" label="Xác nhận mật khẩu" type="password" autocomplete="new-password" minlength="8" maxlength="255" placeholder="Nhập lại mật khẩu" required />
                </div>
                @error('status')<p class="wc-auth-field-error" role="alert">{{ $message }}</p>@enderror
                <p class="wc-register-terms">Bằng việc tạo tài khoản, bạn đồng ý với <a href="{{ route('campaign.rules') }}">Thể lệ chương trình</a>.</p>
                <button type="submit" class="wc-button wc-auth-submit" data-account-submit @disabled($dealers->isEmpty())>Tạo tài khoản</button>
                <p class="wc-register-login">Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a></p>
            </div>
        </form>
    </section>
</x-competition.account-layout>
