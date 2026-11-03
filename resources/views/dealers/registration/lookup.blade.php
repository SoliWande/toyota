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
                    <x-dealer-registration.lookup-waiting-group :result="$result" :values="$values" />
                @else
                    <div class="dr-card dr-lookup-result" data-lookup-kind="{{ $result['kind'] }}">
                        <div class="dr-lookup-summary">
                            <div class="dr-lookup-person"><x-dealer-registration.lookup-avatar :name="$result['masked_name']" class="dr-lookup-avatar" /><div><h3>{{ $result['masked_name'] }}</h3><p>{{ $result['vehicle'] }} @if (!empty($values['license_plate'])) · {{ $values['license_plate'] }} @endif</p></div>@if ($result['kind'] !== 'found')<span class="dr-status-chip dr-lookup-chip-{{ $result['kind'] }}">{{ ['draft' => 'Chưa hoàn tất', 'supplement' => 'Cần bổ sung', 'not_requested' => 'Chờ vào group', 'requested' => 'Chờ duyệt', 'member' => 'Thành viên', 'blocked' => 'Chưa xác nhận'][$result['kind']] }}</span>@endif</div>
                            @if ($result['kind'] === 'draft')
                                <ol class="dr-timeline dr-lookup-draft-steps" aria-label="Các bước đăng ký">
                                    <li class="is-current" aria-current="step"><strong>Hoàn tất đăng ký</strong><p>Bạn mới xong bước 1, còn bước 2 (xe và Facebook).</p></li>
                                    <li class="is-upcoming"><strong>Vào group Facebook</strong></li>
                                    <li class="is-upcoming"><strong>Thành viên chính thức</strong></li>
                                </ol>
                            @elseif ($result['kind'] !== 'found')
                                <x-dealer-registration.lookup-state :result="$result" :dealer="$dealer" />
                            @else
                                <h3 class="dr-lookup-found-title">Đã tìm thấy đăng ký</h3>
                                <p>Thông tin tra cứu khớp với một đăng ký. Kết quả này không xác nhận tư cách thành viên Facebook.</p>
                            @endif
                        </div>
                        @if ($result['kind'] !== 'requested')<div class="dr-lookup-next">
                            @if ($result['kind'] === 'draft')
                                <h3>Còn một bước nữa</h3><p>Điền thông tin xe và Facebook, mất khoảng 1 phút.</p>
                                <a class="dr-button" href="{{ route('dealers.registrations.step-two', $dealer->slug) }}">Tiếp tục đăng ký</a>
                            @elseif ($result['kind'] !== 'found')
                                <x-dealer-registration.lookup-next :result="$result" :dealer="$dealer" />
                            @else
                                <h3>Cộng đồng Toyota Veloz &amp; Hilux</h3>
                                <x-dealer-registration.link :url="config('dealer-registration.group_url')" :group="true" label="Mở group Facebook" class="dr-button dr-button-dark" />
                            @endif
                        </div>@endif
                    </div>
                @endif
            </section>
        @endif
        @endif
    </section>
</x-dealer-registration.layout>
