@props(['result', 'dealer'])
@php($kind = $result['kind'])
@php($contactPhone = preg_replace('/[^0-9+]/', '', $dealer->phone ?? ''))
@php($hasDealerPhone = preg_match('/^\+?[0-9]{8,15}$/D', $contactPhone))
@if ($kind === 'supplement')
    @if (isset($result['supplement_key']))
        <form method="POST" action="{{ route('dealers.lookup.supplement', $dealer->slug) }}" data-registration-supplement novalidate autocomplete="off">
            @csrf
            <input type="hidden" name="supplement_key" value="{{ $result['supplement_key'] }}">
            <input type="hidden" name="verification_version" value="{{ $result['verification_version'] }}">
            <div hidden aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off" aria-label="Website"></div>
            <x-dealer-registration.field prefix="supplement" name="facebook_profile_url" label="Cập nhật link Facebook" type="url" :required="true" :value="$result['supplement_value'] ?? ''" :messages="$result['supplement_errors']['facebook_profile_url'] ?? []" :live-errors="true" maxlength="2048" placeholder="facebook.com/ten-cua-ban" help="Facebook › Trang cá nhân › ··· › Sao chép liên kết" />
            <button class="dr-button" type="submit">Gửi bổ sung</button><p class="dr-help" data-supplement-loading role="status" hidden>Đang gửi bổ sung…</p>
        </form>
    @else
        <h3>Liên hệ để bổ sung thông tin</h3><p>Vui lòng liên hệ Dealer hoặc Admin. Tra cứu không cấp quyền chỉnh sửa hồ sơ; cập nhật trực tuyến cần phiên đăng ký còn hiệu lực.</p>
    @endif
@elseif ($kind === 'blocked')
    <h3>Chưa thể xác nhận đăng ký của bạn</h3><p class="dr-lookup-reason-mobile">{{ $result['reason'] }}</p><p>@if ($hasDealerPhone)Nếu đây là xe của bạn, gọi đại lý {{ $dealer->phone }} để được hỗ trợ.@else Vui lòng liên hệ Dealer hoặc Admin để được hỗ trợ đối chiếu thông tin.@endif</p>
@elseif ($kind === 'not_requested')
    <h3>Bước tiếp: vào group</h3><p>Khi xin vào group, trả lời đúng số điện thoại và biển số bạn đã đăng ký.</p>
    <x-dealer-registration.link :url="config('dealer-registration.group_url')" :group="true" label="Vào group Facebook" class="dr-button" />
@elseif ($kind === 'member')
    <h3>Chào mừng bạn vào nhóm</h3><p>Xem ưu đãi và lịch sự kiện mới nhất trong group.</p>
    <x-dealer-registration.link :url="config('dealer-registration.group_url')" :group="true" label="Mở group Facebook" class="dr-button dr-button-dark" />
@endif
@if ($kind === 'blocked' || ($kind === 'supplement' && ! isset($result['supplement_key'])))
    @if ($hasDealerPhone)<a class="dr-button dr-button-muted" href="tel:{{ $contactPhone }}">Gọi đại lý {{ $dealer->phone }}</a>@endif
@endif
