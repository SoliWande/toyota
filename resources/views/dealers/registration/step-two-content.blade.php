@inject('directory', 'App\Services\AdministrativeDivisions')
@php
    $preview = ($preview ?? false) && app()->environment(['local', 'testing']);
    $backUrl = $preview ? route('dealer-registration.preview', 'landing').'#registration' : route('dealers.profile', $dealer->slug).'#registration';
    $province = $values['province_code'] ?? '';
    $wards = is_string($province) ? $directory->wards($province) : [];
    $validationErrors = $validationErrors ?? [];
    // Presentation only: normalize supplied Laravel errors without adding validation or duplicate checks.
    $fieldMessages = [
        'province_code' => ['Vui lòng chọn Tỉnh/Thành phố.', 'Vui lòng chọn Tỉnh/Thành phố.'],
        'ward_code' => ['Vui lòng chọn Phường/Xã.', 'Vui lòng chọn Phường/Xã.'],
        'address_line' => ['Vui lòng nhập địa chỉ chi tiết.', 'Địa chỉ chi tiết không được vượt quá 500 ký tự.'],
        'vehicle_description' => ['Vui lòng chọn dòng xe.', 'Vui lòng chọn dòng xe.'],
        'license_plate' => ['Vui lòng nhập biển số xe.', 'Biển số xe không hợp lệ.'],
        'first_registration_year' => ['Vui lòng chọn năm đăng ký lần đầu.', 'Năm đăng ký không hợp lệ.'],
        'odo' => ['Vui lòng nhập số km hợp lệ.', 'Vui lòng nhập số km hợp lệ.'],
        'facebook_url' => ['Vui lòng nhập liên kết Facebook cá nhân.', 'Liên kết Facebook không hợp lệ.'],
        'consent' => ['Vui lòng đồng ý điều khoản để tiếp tục.', 'Vui lòng đồng ý điều khoản để tiếp tục.'],
    ];
    foreach ($validationErrors as $field => $messages) {
        if (empty($messages)) {
            unset($validationErrors[$field]);
            continue;
        }
        if (! isset($fieldMessages[$field])) continue;
        $messages = (array) $messages;
        $message = $fieldMessages[$field][filled($values[$field] ?? null) ? 1 : 0];
        if ($field === 'license_plate' && preg_grep('/^(unique|Biển số xe này đã được đăng ký\.)$|already been taken|đã.*đăng ký|trùng/iu', $messages)) {
            $message = 'Biển số xe này đã được đăng ký.';
        }
        $validationErrors[$field] = [$message];
    }
@endphp
<section class="dr-container dr-step-two" aria-labelledby="dr-step-two-title">
    <a class="dr-back" href="{{ $backUrl }}" data-back-step>‹ &nbsp; Quay lại bước 1</a>
    <div class="dr-step-heading"><div><p class="dr-step-eyebrow">BƯỚC 2/2 · THÔNG TIN XE &amp; ĐỊA CHỈ</p><h1 id="dr-step-two-title" tabindex="-1">Thêm vài thông tin về xe</h1><p>Để ban quản trị xác nhận bạn là chủ xe.</p></div><div class="dr-progress" aria-label="Bước 2 trên 2"><span class="is-complete"></span><span class="is-complete"></span></div></div>
    <form method="{{ $preview ? 'dialog' : 'POST' }}" @unless ($preview) action="{{ route('dealers.registrations.store', $dealer->slug) }}" @endunless data-step-two data-ui-form class="dr-step-two-form" novalidate @if ($preview) aria-describedby="dr-step-two-notice" @endif>
        @unless ($preview)
            @csrf
            <input type="hidden" name="_flow" value="two-step">
            <input type="hidden" name="_draft" value="{{ $draftId ?? '' }}">
            <div hidden aria-hidden="true"><label for="dr-final-website">Website</label><input id="dr-final-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
        @endunless
        <div class="dr-split"><div class="dr-main-form dr-card">
            <section class="dr-form-section" data-customer-address data-wards-url="{{ $preview ? route('dealer-registration.preview.wards', '__PROVINCE__') : route('dealers.address.wards', ['slug' => $dealer->slug, 'province' => '__PROVINCE__']) }}"><h2>Địa chỉ</h2><p class="dr-section-help">Nơi bạn sinh sống, để đại lý hỗ trợ.</p><div class="dr-field-row">
                <div class="dr-field" data-field="province_code"><label for="dr-province_code">Tỉnh / Thành phố *</label><select id="dr-province_code" class="dr-input" name="province_code" required autocomplete="address-level1" aria-invalid="{{ isset($validationErrors['province_code']) ? 'true' : 'false' }}" aria-describedby="dr-province_code-error"><option value="">Chọn Tỉnh / Thành phố</option>@foreach ($directory->provinces() as $item)<option value="{{ $item['code'] }}" @selected($province === $item['code'])>{{ $item['name'] }}</option>@endforeach</select><p id="dr-province_code-error" class="dr-error" aria-live="polite" aria-atomic="true" data-error-for="province_code" @if (! isset($validationErrors['province_code'])) hidden @endif>{{ implode(' ', $validationErrors['province_code'] ?? []) }}</p></div>
                <div class="dr-field" data-field="ward_code"><label for="dr-ward_code">Phường / Xã *</label><select id="dr-ward_code" class="dr-input" name="ward_code" required autocomplete="address-level2" @disabled(! count($wards)) aria-invalid="{{ isset($validationErrors['ward_code']) ? 'true' : 'false' }}" aria-describedby="dr-ward_code-error dr-address-status"><option value="">{{ count($wards) ? 'Chọn Phường / Xã' : 'Chọn Tỉnh / Thành trước' }}</option>@foreach ($wards as $ward)<option value="{{ $ward['code'] }}" @selected(($values['ward_code'] ?? '') === $ward['code'])>{{ $ward['name'] }}</option>@endforeach</select><p id="dr-ward_code-error" class="dr-error" aria-live="polite" aria-atomic="true" data-error-for="ward_code" @if (! isset($validationErrors['ward_code'])) hidden @endif>{{ implode(' ', $validationErrors['ward_code'] ?? []) }}</p></div>
            </div><p id="dr-address-status" data-address-status role="status" aria-live="polite" class="dr-help"></p><button data-address-retry type="button" class="dr-text-link" hidden>Thử tải lại Phường / Xã</button>
            <x-dealer-registration.field :live-errors="true" name="address_line" label="Địa chỉ chi tiết" :required="true" :value="$values['address_line'] ?? ''" :messages="$validationErrors['address_line'] ?? []" autocomplete="street-address" maxlength="500" placeholder="Số nhà, tên đường hoặc thôn/ấp" />
            </section>
            <section class="dr-form-section"><h2>Thông tin xe</h2><p class="dr-section-help">Biển số và năm đăng ký giúp xác nhận bạn là chủ xe.</p><div class="dr-field" data-field="vehicle_description"><input type="hidden" name="vehicle_description" disabled value="{{ $values['vehicle_description'] ?? '' }}"><div class="dr-selected-car"><x-dealer-registration.icon name="car" :size="22" /><strong data-selected-car>{{ $values['vehicle_description'] ?? 'Chưa chọn dòng xe' }}</strong><a href="{{ $backUrl }}" id="dr-step-two-vehicle_description" class="dr-text-link" data-back-step data-validation-control="vehicle_description" aria-invalid="{{ isset($validationErrors['vehicle_description']) ? 'true' : 'false' }}" aria-describedby="dr-step-two-vehicle_description-error">Đổi</a></div><p id="dr-step-two-vehicle_description-error" class="dr-error" aria-live="polite" aria-atomic="true" data-error-for="vehicle_description" @if (empty($validationErrors['vehicle_description'])) hidden @endif>{{ implode(' ', $validationErrors['vehicle_description'] ?? []) }}</p></div><div class="dr-field-row dr-vehicle-row">
                <x-dealer-registration.field :live-errors="true" name="license_plate" label="Biển số xe" :required="true" :value="$values['license_plate'] ?? ''" :messages="$validationErrors['license_plate'] ?? []" placeholder="VD: 51K-123.45" maxlength="20" help="Ghi đúng như trên biển số xe." />
                <div class="dr-field" data-field="first_registration_year"><label for="dr-first_registration_year">Năm đăng ký lần đầu *</label><select id="dr-first_registration_year" class="dr-input" name="first_registration_year" required aria-invalid="{{ isset($validationErrors['first_registration_year']) ? 'true' : 'false' }}" aria-describedby="dr-first_registration_year-error"><option value="">Chọn năm</option>@if (filled($values['first_registration_year'] ?? null) && ! in_array((string) $values['first_registration_year'], array_map('strval', range(1950, (int) date('Y'))), true))<option value="{{ $values['first_registration_year'] }}" selected disabled>{{ $values['first_registration_year'] }}</option>@endif
                    @for ($year = (int) date('Y'); $year >= 1950; $year--)<option value="{{ $year }}" @selected((string) ($values['first_registration_year'] ?? '') === (string) $year)>{{ $year }}</option>@endfor</select><p id="dr-first_registration_year-error" class="dr-error" aria-live="polite" aria-atomic="true" data-error-for="first_registration_year" @if (! isset($validationErrors['first_registration_year'])) hidden @endif>{{ implode(' ', $validationErrors['first_registration_year'] ?? []) }}</p></div>
                <x-dealer-registration.field :live-errors="true" name="odo" label="Số ODO hiện tại (không bắt buộc)" type="text" :value="$values['odo'] ?? ''" :messages="$validationErrors['odo'] ?? []" pattern="[0-9]*" inputmode="numeric" placeholder="VD: 25000" help="Đơn vị: km" />
            </div></section>
            <section class="dr-form-section"><h2>Tài khoản Facebook</h2><p class="dr-section-help">Dùng đúng tài khoản bạn sẽ dùng để vào group.</p><div class="dr-facebook-row"><x-dealer-registration.field :live-errors="true" name="facebook_url" label="Link trang cá nhân Facebook" :required="true" :value="$values['facebook_url'] ?? ''" :messages="$validationErrors['facebook_url'] ?? []" inputmode="url" autocomplete="url" maxlength="2048" placeholder="facebook.com/ten-cua-ban" /><p class="dr-facebook-help">Cách lấy link: Mở Facebook › Trang cá nhân › ⋯ › Sao chép liên kết</p></div></section>
            <div class="dr-form-submit"><div class="dr-field" data-field="consent"><label class="dr-consent"><input id="dr-consent" type="checkbox" name="consent" value="1" required @checked($values['consent'] ?? false) aria-invalid="{{ isset($validationErrors['consent']) ? 'true' : 'false' }}" aria-describedby="dr-consent-error"><span>{{ config('dealer.consent_notice') }} <x-dealer-registration.link :url="config('dealer-registration.terms_url')" label="Xem điều khoản" /></span></label><p id="dr-consent-error" class="dr-error" aria-live="polite" aria-atomic="true" data-error-for="consent" @if (! isset($validationErrors['consent'])) hidden @endif>{{ implode(' ', $validationErrors['consent'] ?? []) }}</p><p class="dr-help"><x-dealer-registration.rules-link class="dr-text-link" /></p></div><button type="submit" class="dr-button" @if ($preview) disabled data-ui-submit @endif>Hoàn tất đăng ký</button></div>
        </div><aside class="dr-sidebar"><section class="dr-side-card"><div class="dr-side-heading"><h2>Thông tin bước 1</h2><a href="{{ $backUrl }}" class="dr-text-link" data-back-step>Sửa</a></div><dl class="dr-summary-data"><div><dt>Họ và tên</dt><dd data-summary-name>{{ $values['customer_name'] ?? 'Chưa nhập' }}</dd></div><div><dt>Số điện thoại</dt><dd data-summary-phone>{{ $values['phone'] ?? 'Chưa nhập' }}</dd></div><div><dt>Dòng xe</dt><dd data-selected-car>{{ $values['vehicle_description'] ?? 'Chưa chọn' }}</dd></div></dl></section><section class="dr-side-card"><h2>Sau khi gửi</h2><ol class="dr-after-submit"><li>Vào group Facebook bằng tài khoản đã khai báo</li><li>Trả lời thông tin đăng ký khi xin vào group</li><li>Chờ ban quản trị xác minh và duyệt</li></ol></section><x-dealer-registration.dealer-card :dealer="$dealer" :compact="true" /></aside></div>
        @if ($preview)<p id="dr-step-two-notice" class="dr-phase-note" role="status">Đây là giao diện xem trước, không gửi đăng ký.</p>@endif

    </form>
</section>
