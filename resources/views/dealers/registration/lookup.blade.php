<x-dealer-registration.layout :dealer="$dealer" title="Tra cứu đăng ký" :lookup="true" :success="($result['kind'] ?? null) === 'supplemented'">
    <section class="dr-container dr-lookup-content" aria-labelledby="dr-lookup-title">
        @if (($result['kind'] ?? null) === 'supplemented')
            <div class="dr-supplement-success" data-lookup-kind="supplemented">
                <div><picture><source media="(min-width:768px)" srcset="{{ asset('dealer-registration/lookup-success.svg') }}" width="180" height="180"><img src="{{ asset('dealer-registration/lookup-success-mobile.svg') }}" width="96" height="96" alt=""></picture></div>
                <div class="dr-card"><h1 id="dr-lookup-title" tabindex="-1" data-lookup-result-focus>Đã gửi bổ sung</h1><p>Hệ thống đã nhận thông tin bổ sung. Kết quả sẽ được cập nhật ở trang tra cứu sau khi kiểm tra lại.</p><div class="dr-supplement-actions"><a class="dr-button" href="{{ route('dealers.lookup.show', $dealer->slug) }}">Xem lại trạng thái</a><a class="dr-button dr-button-muted" href="{{ route('home') }}">Về trang Cộng đồng Toyota</a></div></div>
            </div>
        @else
        <div class="dr-lookup-heading">
            <h1 id="dr-lookup-title"><span class="dr-lookup-desktop">Kiểm tra trạng thái đăng ký</span><span class="dr-lookup-mobile">Kiểm tra trạng thái</span></h1>
            <p>Nhập số điện thoại và biển số xe bạn đã dùng khi đăng ký.</p>
        </div>
        <form class="dr-card dr-lookup-form" method="POST" action="{{ route('dealers.lookup.store', $dealer->slug) }}" data-registration-lookup novalidate autocomplete="off">
            @csrf
            <div hidden aria-hidden="true"><label for="lookup-website">Website</label><input id="lookup-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
            <x-dealer-registration.field prefix="lookup" name="phone" label="Số điện thoại" type="tel" :required="true" :value="$values['phone'] ?? ''" :messages="$errors['phone'] ?? []" :live-errors="true" maxlength="30" inputmode="tel" />
            <x-dealer-registration.field prefix="lookup" name="license_plate" label="Biển số xe" :required="true" :value="$values['license_plate'] ?? ''" :messages="$errors['license_plate'] ?? []" :live-errors="true" maxlength="30" autocapitalize="characters" spellcheck="false" />
            <button class="dr-button dr-button-dark" type="submit" data-lookup-submit><img src="{{ asset('dealer-registration/lookup-submit.svg') }}" width="18" height="18" alt="">Tra cứu</button>
            @if (isset($errors['lookup']) || isset($errors['website']))<p class="dr-error dr-lookup-form-notice" role="alert">{{ implode(' ', $errors['lookup'] ?? ['Không thể tra cứu. Vui lòng kiểm tra thông tin.']) }}</p>@endif
            <p class="dr-lookup-loading dr-help" role="status" aria-live="polite" hidden>Đang tra cứu…</p>
        </form>
        @if ($result)
            <section class="dr-lookup-results" aria-labelledby="dr-lookup-result-label">
                <h2 id="dr-lookup-result-label" tabindex="-1" data-lookup-result-focus>KẾT QUẢ</h2>
                @if ($result['kind'] === 'not_found')
                    <div class="dr-card dr-lookup-empty" role="status" data-lookup-kind="not_found">
                        <span class="dr-lookup-empty-icon" aria-hidden="true"><img src="{{ asset('dealer-registration/lookup-empty.svg') }}" width="38" height="38" alt=""></span>
                        <div><h3>Không tìm thấy đăng ký</h3><p>Kiểm tra lại số điện thoại và biển số. Nếu chưa đăng ký, bạn có thể đăng ký ngay.</p></div>
                        @if ($dealer->is_active)<a class="dr-button" href="{{ route('dealers.profile', $dealer->slug) }}#registration">Đăng ký tham gia</a>@endif
                    </div>
                @elseif ($result['kind'] === 'not_requested')
                    <x-dealer-registration.lookup-waiting-group :result="$result" :values="$values" :dealer="$dealer" />
                @else
                    <div class="dr-card dr-lookup-result" data-lookup-kind="{{ $result['kind'] }}">
                        <div class="dr-lookup-summary">
                            <x-dealer-registration.lookup-person :result="$result" :values="$values" />
                            <x-dealer-registration.lookup-state :result="$result" />
                        </div>
                        @if (! in_array($result['kind'], ['requested', 'found'], true))<div class="dr-lookup-next" data-lookup-sidebox>
                            <x-dealer-registration.lookup-next :result="$result" :dealer="$dealer" :values="$values" />
                        </div>@endif
                    </div>
                @endif
            </section>
        @endif
        @endif
    </section>
</x-dealer-registration.layout>
