@foreach (['create' => null, 'edit' => $selected] as $mode => $editing)
    @if ($mode === 'create' || $editing)
        <x-admin.modal :id="'dc-'.$mode" :title="$mode === 'create' ? 'Thêm đại lý' : 'Sửa thông tin đại lý'" :subtitle="$editing ? $editing->name.' · '.$editing->code : 'Đại lý mới sẽ có mã QR và standee ngay sau khi tạo.'" class="dc-modal">
            <form id="dc-form-{{ $mode }}" method="POST" action="{{ $editing ? route('admin.dealers.update', $editing) : route('admin.dealers.store') }}" data-dc-form>
                @csrf @if ($editing) @method('PUT') @endif
                <input type="hidden" name="is_active" value="{{ $editing ? (int) $editing->is_active : 1 }}">
                <div class="dc-fields">
                    @foreach (['name' => ['Tên đại lý', 'VD: Toyota Cần Thơ', 255], 'code' => ['Mã đại lý', 'VD: TMV-1040', 50], 'province' => ['Tỉnh / Thành phố', 'Chọn tỉnh/thành', 255], 'phone' => ['Điện thoại', 'Số tổng đài', 30], 'address' => ['Địa chỉ', 'Số nhà, đường, phường', 2000], 'opening_hours' => ['Giờ mở cửa', 'VD: 8:00 – 17:00', 2000], 'facebook_url' => ['Link Facebook', 'facebook.com/…', 2048], 'zalo_url' => ['Link Zalo', 'zalo.me/…', 2048]] as $field => [$label, $placeholder, $max])
                        <label for="dc-{{ $mode }}-{{ $field }}">{{ $label }}
                            @if ($field === 'province')<select id="dc-{{ $mode }}-{{ $field }}" name="province"><option value="">Chọn tỉnh/thành</option>@foreach ($formProvinces as $province)<option @selected($editing?->province === $province)>{{ $province }}</option>@endforeach</select>
                            @else<input id="dc-{{ $mode }}-{{ $field }}" name="{{ $field }}" value="{{ $editing?->$field }}" placeholder="{{ $placeholder }}" maxlength="{{ $max }}" @required(in_array($field, ['name', 'code'])) type="{{ $field === 'phone' ? 'tel' : 'text' }}">@endif
                            <small data-dc-field-error="{{ $field }}" hidden></small>
                        </label>
                    @endforeach
                </div>
                <div class="dc-note"><img src="{{ asset('admin/icons/c04-info.png') }}" width="16" height="16" alt=""><p>{{ $editing ? 'Đổi mã đại lý không làm đổi link QR đã in.' : 'Quản lý đại lý tự đăng ký tài khoản, admin duyệt ở mục Duyệt tài khoản.' }}</p></div>
                <p data-dc-form-error class="dc-error" role="alert" hidden></p>
            </form>
            <x-slot:footer><x-admin.button variant="secondary" size="44" data-dc-close>Huỷ</x-admin.button><x-admin.button type="submit" size="44" :form="'dc-form-'.$mode">{{ $editing ? 'Lưu thay đổi' : 'Tạo đại lý' }}</x-admin.button></x-slot:footer>
        </x-admin.modal>
    @endif
@endforeach
@if ($selected)
    <x-admin.modal id="dc-stop" title="Ngừng tham gia chương trình?" :subtitle="$selected->name.' · '.$selected->code" variant="compact" class="dc-modal dc-stop">
        <form id="dc-form-stop" method="POST" action="{{ route('admin.dealers.status', $selected) }}" data-dc-form>
            @csrf @method('PATCH')<input type="hidden" name="is_active" value="0">
            {{-- BLOCKED: Figma claims about error landing and future ranking contradict current backend. --}}
            <div class="dc-stop-lines"><p><img src="{{ asset('admin/icons/c04-tick.png') }}" width="16" height="16" alt="">Điểm và lịch sử đã có vẫn giữ.</p></div>
            <p data-dc-form-error class="dc-error" role="alert" hidden></p>
        </form>
        <x-slot:footer><x-admin.button variant="secondary" size="44" data-dc-close>Huỷ</x-admin.button><x-admin.button variant="danger" type="submit" size="44" form="dc-form-stop">Ngừng tham gia</x-admin.button></x-slot:footer>
    </x-admin.modal>
@endif
