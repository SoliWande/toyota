@props(['result', 'dealer'])
@php
    $kind = $result['kind'];
    $group = in_array($kind, ['requested', 'member']) || ($result['group_confirmed'] ?? false);
    $labels = ['supplement' => 'Cần bổ sung', 'not_requested' => 'Chờ vào group', 'requested' => 'Chờ duyệt', 'member' => 'Thành viên', 'blocked' => 'Chưa xác nhận'];
@endphp
<ol class="dr-timeline dr-lookup-draft-steps" aria-label="Tiến trình đăng ký và Facebook Group">
    <li class="is-done"><strong>Đăng ký thông tin</strong>@if (! in_array($kind, ['member', 'blocked']))<p>Đã nhận thông tin của bạn</p>@endif</li>
    <li class="{{ $group ? 'is-done' : ($kind === 'not_requested' ? 'is-current' : 'is-upcoming') }}"><strong>Vào group Facebook</strong>
        @if ($group && ! in_array($kind, ['member', 'blocked']))<p>Đã gửi yêu cầu vào group</p>@elseif (! $group && $kind === 'not_requested')<p>Xin vào group và trả lời đúng số điện thoại, biển số.</p>@elseif (! $group)<p>Chỉ hiển thị hoàn tất sau khi Admin xác nhận.</p>@endif
    </li>
    <li class="{{ $kind === 'member' ? 'is-done' : ($kind === 'requested' ? 'is-waiting' : ($kind === 'supplement' ? 'is-warning' : 'is-upcoming')) }}">
        <strong>{{ $kind === 'supplement' ? 'Cần sửa lại thông tin' : ($kind === 'blocked' ? 'Chưa thể xác nhận' : 'Thành viên chính thức') }}</strong>
        @if (isset($result['reason']))<p>{{ $result['reason'] }}</p>@elseif ($kind === 'requested')<p>Đang chờ quản trị viên nhóm chấp nhận.</p>@elseif ($kind === 'member')<p>Bạn đã là thành viên chính thức của nhóm.</p>@endif
    </li>
</ol>
