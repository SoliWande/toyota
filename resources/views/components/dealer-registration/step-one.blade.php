@props(['dealer', 'preview' => false, 'validationErrors' => [], 'values' => [], 'duplicatePhone' => false])
@php
    // Duplicate is an explicit backend validation result; never inferred from a phone value or query parameter.
    $duplicatePhone = $duplicatePhone && ! empty($validationErrors['phone']);
@endphp
<section id="registration" class="dr-registration dr-container" aria-labelledby="dr-registration-title">
    @if (session('dealer_draft_notice'))<p class="dr-phase-note" role="status">{{ session('dealer_draft_notice') }}</p>@endif
    @if (! $dealer->is_active)
        <div class="dr-invalid-panel">
            <div class="dr-invalid-art"><img src="{{ asset('dealer-registration/qr-invalid.png') }}" width="160" height="160" alt=""></div>
            <div><h2 id="dr-registration-title">Mã QR không còn hiệu lực</h2><p>Đại lý hiện không tiếp nhận đăng ký mới. Bạn hỏi nhân viên đại lý để được hỗ trợ nhé.</p><a href="{{ route('home') }}" class="dr-button">Về trang Cộng đồng Toyota</a></div>
        </div>
    @else
        <div class="dr-step-one dr-card">
            <div class="dr-intro">
                <span class="dr-chip"><x-dealer-registration.icon name="dealer" :size="11" /><span>Đại lý giới thiệu: <strong>{{ $dealer->name }}</strong></span></span>
                <div class="dr-progress" aria-label="Bước 1 trên 2"><span class="is-complete"></span><span></span></div>
                <h2 id="dr-registration-title">Đăng ký tham gia</h2>
                <p>Bước 1/2 · mất khoảng 1 phút, miễn phí</p>
                <small class="dr-privacy"><x-dealer-registration.icon name="lock" :size="16" />Chỉ dùng để xác nhận chủ xe, không công khai</small>
            </div>
            <form method="{{ $preview ? 'dialog' : 'POST' }}" @unless ($preview) action="{{ route('dealers.registrations.step-one', $dealer->slug) }}" @endunless class="dr-step-one-fields" data-step-one data-ui-form novalidate>
                @unless ($preview) @csrf @endunless
                <div hidden aria-hidden="true"><label for="dr-website">Website</label><input id="dr-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
                <div class="dr-field-row">
                    <x-dealer-registration.field name="customer_name" label="Họ và tên" :required="true" :value="$values['customer_name'] ?? ''" :messages="$validationErrors['customer_name'] ?? []" placeholder="Họ và tên của bạn" autocomplete="name" maxlength="255" />
                    <x-dealer-registration.field name="phone" label="Số điện thoại" type="tel" :required="true" :value="$values['phone'] ?? ''" :messages="$validationErrors['phone'] ?? []" placeholder="Số điện thoại của bạn" inputmode="tel" autocomplete="tel" maxlength="30" />
                </div>
                @if ($duplicatePhone)
                    <div class="dr-duplicate" role="alert"><div><strong>Số điện thoại này đã đăng ký tham gia cộng đồng.</strong><p>Bạn chưa từng đăng ký? Vui lòng liên hệ đại lý để kiểm tra.</p></div>
                        <a href="{{ route('dealers.lookup.show', $dealer->slug) }}" class="dr-text-link">Xem trạng thái đăng ký</a>
                        <x-dealer-registration.link :url="config('dealer-registration.group_url')" :group="true" label="Vào group Facebook" class="dr-button dr-button-dark" />
                    </div>
                @endif
                <fieldset class="dr-car-picker dr-field" data-field="vehicle_description">
                    <legend>Bạn đang đi xe <span aria-hidden="true">*</span></legend>
                    <div class="dr-car-options">
                        @foreach (\App\Services\DealerRegistrationDraft::VEHICLES as $car)
                            <label><input type="radio" name="vehicle_description" value="{{ $car }}" required @checked(($values['vehicle_description'] ?? '') === $car) aria-invalid="{{ empty($validationErrors['vehicle_description']) ? 'false' : 'true' }}" aria-describedby="dr-vehicle_description-error"><span>{{ $car }}</span></label>
                        @endforeach
                    </div>
                    <p id="dr-vehicle_description-error" class="dr-error" data-error-for="vehicle_description" @if (empty($validationErrors['vehicle_description'])) hidden @endif>{{ implode(' ', $validationErrors['vehicle_description'] ?? []) }}</p>
                </fieldset>
                <div class="dr-form-actions"><span class="dr-help">Đã đăng ký rồi? <a class="dr-text-link" href="{{ route('dealers.lookup.show', $dealer->slug) }}">Tra cứu trạng thái</a></span><button class="dr-button" type="submit" @if ($preview) disabled data-ui-submit @endif>Tiếp tục <x-dealer-registration.icon name="arrow" /></button></div>
            </form>
        </div>
    @endif
</section>
