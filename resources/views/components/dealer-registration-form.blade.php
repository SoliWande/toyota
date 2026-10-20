@props(['dealer'])
<section id="registration" class="mt-8 scroll-mt-6 rounded-2xl border border-stone-200 bg-white p-5 sm:p-7" aria-labelledby="registration-title">
    <h2 id="registration-title" class="text-2xl font-bold">Đăng ký cùng {{ $dealer->name }}</h2>
    <p class="mt-3 text-sm leading-6 text-stone-600">Để lại thông tin để đại lý liên hệ và xác minh đăng ký của bạn. Đăng ký chỉ được ghi nhận vào chiến dịch sau khi Admin duyệt.</p>
    @if (session('registration_success'))
        <p role="status" class="mt-5 rounded-xl bg-green-50 p-4 text-green-900">{{ session('registration_success') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-5 rounded-xl bg-red-50 p-4 text-red-900">Thông tin chưa hợp lệ. Vui lòng kiểm tra các trường và gửi lại.</div>
    @endif
    <form method="POST" action="{{ route('dealers.registrations.store', ['slug' => $dealer->slug]) }}" class="mt-6 space-y-5">
        @csrf
        <div>
            <label for="customer_name" class="text-sm font-medium">Họ và tên <span aria-hidden="true">*</span></label>
            <input id="customer_name" name="customer_name" type="text" required maxlength="255" autocomplete="name" class="form-input">
            @error('customer_name')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="phone" class="text-sm font-medium">Số điện thoại <span aria-hidden="true">*</span></label>
            <input id="phone" name="phone" type="tel" inputmode="tel" required maxlength="30" pattern="[+0-9\s.\(\)\-]{10,30}" autocomplete="tel" class="form-input" aria-describedby="phone-help">
            <p id="phone-help" class="mt-2 text-xs text-stone-500">Số điện thoại Việt Nam, có thể dùng đầu số 0 hoặc +84.</p>
            @error('phone')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <x-customer-address-fields :submission="new \App\Models\CustomerSubmission" :required="true" :restore-input="false" :wards-url="route('dealers.address.wards', ['slug' => $dealer->slug, 'province' => '__PROVINCE__'])" />
        <div>
            <label for="vehicle_description" class="text-sm font-medium">Xe đang sử dụng <span aria-hidden="true">*</span></label>
            <input id="vehicle_description" name="vehicle_description" type="text" required maxlength="255" placeholder="Hãng xe, dòng xe bạn đang sử dụng" class="form-input">
            @error('vehicle_description')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <div hidden aria-hidden="true"><label for="registration-website">Website</label><input id="registration-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
        <label class="flex items-start gap-3 rounded-xl bg-stone-50 p-4 text-sm leading-6">
            <input type="checkbox" name="consent" value="1" required class="mt-1 h-5 w-5 shrink-0 accent-red-700">
            <span>{{ config('dealer.consent_notice') }}</span>
        </label>
        @error('consent')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
        <button type="submit" class="button-primary min-h-12 w-full sm:w-auto">Gửi đăng ký</button>
        <p class="text-xs leading-5 text-stone-500">Không gửi thông tin nhạy cảm trong trường thông tin xe hoặc địa chỉ. Các trường có dấu * và địa chỉ đều bắt buộc.</p>
    </form>
</section>
