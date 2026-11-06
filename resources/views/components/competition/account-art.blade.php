@props(['status' => null, 'recovery' => false])
<aside class="wc-auth-art" aria-label="Tham gia cộng đồng Toyota">
    <img class="wc-auth-scene" src="{{ asset('competition/login/scene.png') }}" width="780" height="1560" alt="">
    <div class="wc-auth-art-copy"><p class="wc-auth-eyebrow">DÀNH CHO NHÂN VIÊN ĐẠI LÝ</p><h2>@if ($recovery)Đặt lại mật khẩu<br>cho tài khoản của bạn.@elseif ($status === 'rejected')Có nhầm lẫn?<br>Đăng ký lại nhé.@elseif ($status){{ $status === 'pending' ? 'Sắp xong rồi.' : 'Cùng kiểm tra lại.' }}@else Tham gia cùng<br>đại lý của bạn.@endif</h2><p>@if ($recovery)Yêu cầu liên kết bằng email công việc đã đăng ký.@elseif ($status)Trạng thái xét duyệt được cập nhật tại đây.@else Nhân viên do quản lý đại lý duyệt.<br>Quản lý đại lý do Ô tô Sài Gòn duyệt.@endif</p></div>
</aside>
