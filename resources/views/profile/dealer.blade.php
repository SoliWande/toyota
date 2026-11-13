<x-competition.account-layout title="Hồ sơ cá nhân" :staff="true" staff-page="profile">
    <header class="wc-staff-title"><div><p>QUẢN LÝ ĐẠI LÝ</p><h1>Hồ sơ cá nhân</h1></div></header>
    @if (session('success'))<p class="wc-auth-success" role="status">{{ session('success') }}</p>@endif
    @if ($errors->any())<div class="wc-auth-error" role="alert">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <div class="wc-profile-grid">
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="wc-profile-card" data-dealer-profile data-account-form>
            @csrf @method('PATCH')
            <div class="wc-profile-identity">
                <div class="wc-profile-avatar-wrap"><x-dealer-avatar :user="$user" /><img class="wc-profile-preview" alt="Xem trước ảnh đại diện mới" width="96" height="96" data-avatar-preview hidden></div>
                <div class="wc-profile-person"><h2>{{ $user->name }}</h2><p>{{ $user->email }}</p><span class="wc-profile-role">Quản lý đại lý</span></div>
            </div>
            <div class="wc-profile-upload"><label for="avatar">Thay ảnh đại diện</label><input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="avatar-help" @error('avatar') aria-invalid="true" @enderror><p id="avatar-help">JPG, PNG hoặc WebP, tối đa 5 MB và 4096 × 4096 px.</p><p data-avatar-error role="alert" hidden></p></div>
            <div class="wc-profile-divider"></div>
            <h2>Thông tin cá nhân</h2>
            <div class="wc-profile-field"><label for="name">Họ tên</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name" @error('name') aria-invalid="true" @enderror></div>
            <div class="wc-profile-field"><label for="phone">Số điện thoại</label><input id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone', $user->phone) }}" maxlength="30" autocomplete="tel" @error('phone') aria-invalid="true" @enderror></div>
            <div class="wc-profile-field"><span>Email</span><p class="wc-profile-email">{{ $user->email }}</p><small>Email cố định. Để sử dụng email khác, vui lòng đăng ký tài khoản mới.</small></div>
            <div class="wc-profile-actions"><button class="wc-profile-save" type="submit" data-account-submit>Lưu hồ sơ</button></div>
        </form>
        <form method="POST" action="{{ route('profile.password') }}" class="wc-profile-card wc-profile-password" data-account-form>
            @csrf @method('PUT')
            <h2>Đổi mật khẩu</h2>
            <div class="wc-profile-field"><label for="current_password">Mật khẩu hiện tại</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password" maxlength="255" @error('current_password') aria-invalid="true" @enderror></div>
            <div class="wc-profile-field"><label for="password">Mật khẩu mới</label><input id="password" name="password" type="password" required autocomplete="new-password" minlength="8" maxlength="255" @error('password') aria-invalid="true" @enderror></div>
            <div class="wc-profile-field"><label for="password_confirmation">Xác nhận mật khẩu mới</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" maxlength="255"></div>
            <div class="wc-profile-actions"><button class="wc-profile-save" type="submit" data-account-submit>Đổi mật khẩu</button></div>
        </form>
    </div>
</x-competition.account-layout>
