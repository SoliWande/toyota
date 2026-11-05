    <footer class="wc-footer"><div class="wc-container wc-footer-row">
        <img src="{{ asset('dealer-registration/lookup-logo.svg') }}" width="49.8315" height="36" alt="Toyota">
        <p>Chương trình của Toyota Việt Nam · Vận hành bởi Ô tô Sài Gòn</p>
        <a href="{{ route('campaign.rules') }}">Thể lệ</a>
        @php($privacyUrl = config('dealer-registration.privacy_url'))
        @if (is_string($privacyUrl) && preg_match('~^https?://~i', $privacyUrl))<a href="{{ $privacyUrl }}">Bảo mật thông tin</a>@else<span aria-disabled="true">Bảo mật thông tin</span>@endif
        @php($hotline = config('dealer-registration.hotline'))
        @if (is_string($hotline) && preg_match('/\A(?:0[35789][0-9]{8}|02[0-9]{9}|1[89]00[0-9]{4,6})\z/', \App\Services\RegistrationPhone::normalize($hotline)))<a href="tel:{{ \App\Services\RegistrationPhone::normalize($hotline) }}">Hotline {{ $hotline }}</a>@endif
    </div></footer>
