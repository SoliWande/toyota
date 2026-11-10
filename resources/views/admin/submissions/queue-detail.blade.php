@php
    $pending = $selected->status === \App\Enums\SubmissionStatus::Pending;
    $actionContext = [...$filters, 'selected' => $selected->id, 'c02' => 1];
    $legacyUrl = route('admin.submissions.show', ['submission' => $selected, ...$actionContext]);
@endphp
<aside class="cq-detail" data-selected-submission="{{ $selected->id }}">
    <header class="cq-detail-head"><div><h2>{{ $selected->customer_name }}</h2><x-admin.badge :variant="match ($selected->status->value) { 'approved' => 'approved', 'rejected' => 'rejected', default => 'pending' }">{{ \App\Services\AdminSubmissionQueue::TABS[$selected->status->value] }}</x-admin.badge></div><span>{{ $number($selectionPosition) }} / {{ $number($submissions->total()) }}</span>
        @foreach ($selectionLinks as $direction => $url)
            @if ($url)<a class="cq-circle" href="{{ $url }}" aria-label="{{ $direction === 'previous' ? 'Khách trước' : 'Khách sau' }}"><img src="{{ asset('admin/icons/c02-'.$direction.'.png') }}" width="14" height="14" alt=""></a>@else<span class="cq-circle" aria-disabled="true"><img src="{{ asset('admin/icons/c02-'.$direction.'.png') }}" width="14" height="14" alt=""></span>@endif
        @endforeach
    </header>
    <div class="cq-flags-slot" aria-hidden="true"></div>
    <div class="cq-no-duplicates">@if ($noDuplicates)<img src="{{ asset('admin/icons/c02-clear.png') }}" width="18" height="18" alt=""><p>Không trùng SĐT, biển số, Facebook.</p>@endif</div>
    <hr>
    <section class="cq-group"><header><h3>ĐỐI CHIẾU GROUP FACEBOOK</h3><a href="{{ config('dealer-registration.group_url') }}" target="_blank" rel="noopener noreferrer">Mở Facebook<img src="{{ asset('admin/icons/c02-external.png') }}" width="14" height="14" alt=""></a></header>
        @foreach ([['Đã thấy trong yêu cầu vào group / thành viên group', in_array($groupProgress, ['requested', 'member'], true)], ['Câu trả lời SĐT khớp: '.$maskPhone($selected->phone), false], ['Câu trả lời biển số khớp: '.$maskPlate($selected->license_plate), false]] as [$label, $checked])
            <div @class(['cq-check', 'cq-check-verified' => $checked])><span class="cq-checkbox" role="checkbox" aria-disabled="true" aria-checked="{{ $checked ? 'true' : 'false' }}">@if ($checked)<img src="{{ asset('admin/icons/c02-checked.png') }}" width="20" height="20" alt="">@endif</span><p>{{ $label }}</p></div>
        @endforeach
    </section>
    <hr>
    <div class="cq-information">
        <section><header><h3>LIÊN HỆ &amp; XE</h3><button type="button" @disabled(!$editingAvailable) data-cq-edit class="cq-edit"><img src="{{ asset('admin/icons/c02-edit.png') }}" width="13" height="13" alt="">Sửa</button></header>
            <dl><div><dt>Điện thoại</dt><dd>{{ $selected->phone }}</dd></div><div><dt>Địa chỉ</dt><dd>{{ implode(' · ', array_filter([$selected->province_name, $selected->ward_name])) }}</dd></div><div class="cq-tall"><dt>Xe</dt><dd>{{ implode(' · ', array_filter([$selected->vehicle_model ?: $selected->vehicle_description, $selected->first_registration_year, $maskPlate($selected->license_plate)])) }}</dd></div><div class="cq-tall"><dt>Facebook</dt><dd>@if ($selected->facebook_url_normalized)<a href="{{ $selected->facebook_url_normalized }}" target="_blank" rel="noopener noreferrer">{{ \Illuminate\Support\Str::limit(preg_replace('~^https?://(?:www\.)?~', '', $selected->facebook_url_normalized), 48) }}</a>@endif</dd></div></dl>
        </section>
        <section><header><h3>GHI NHẬN CHO</h3><button type="button" @disabled(!$editingAvailable) data-cq-edit class="cq-edit"><img src="{{ asset('admin/icons/c02-edit.png') }}" width="13" height="13" alt="">Sửa</button></header>
            <dl><div class="cq-tall"><dt>Đại lý</dt><dd>{{ $selected->dealer?->name }}@if ($selected->dealer) · {{ $selected->dealer->code }}@endif</dd></div><div><dt>Sales</dt><dd>{{ $selected->source === 'dealer_qr' ? 'Khách tự đăng ký' : ($selected->sales?->role === \App\Enums\UserRole::Sales ? $selected->sales->name : '') }}</dd></div><div><dt>Nguồn</dt><dd>{{ $selected->source === 'dealer_qr' ? 'QR đại lý' : '' }}</dd></div><div class="cq-tall"><dt>Thời điểm</dt><dd>{{ $selected->submitted_at->format('d/m/Y H:i') }} (giờ VN)</dd></div></dl>
        </section>
    </div>
    <hr>
    <div class="cq-actions">
        @if ($pending && $selected->source === 'sales')
            <form method="POST" action="{{ route('admin.submissions.approve', ['submission' => $selected, ...$actionContext]) }}" data-cq-action>@csrf<input type="hidden" name="return_to_queue" value="1"><input type="hidden" name="review_submission_id" value="{{ $selected->id }}"><x-admin.button type="submit" size="38" class="cq-verify"><img src="{{ asset('admin/icons/c02-approve.png') }}" width="16" height="16" alt="">Xác minh</x-admin.button></form>
        @else<x-admin.button :href="$pending ? $legacyUrl.'#submission-review' : null" :disabled="!$pending" size="38" class="cq-verify"><img src="{{ asset('admin/icons/c02-approve.png') }}" width="16" height="16" alt="">Xác minh</x-admin.button>@endif
        <x-admin.button :disabled="!$pending" size="42" variant="warning-soft" class="cq-supplement-button" data-cq-supplement-toggle aria-expanded="true" aria-controls="cq-supplement">Yêu cầu bổ sung</x-admin.button>
        <x-admin.button :href="$pending ? route('admin.submissions.show', ['submission' => $selected, ...$actionContext, 'review' => 'reject']).'#submission-review' : null" :disabled="!$pending" size="42" variant="danger-outline" class="cq-reject">Từ chối</x-admin.button>
    </div>
    <p data-cq-error class="cq-error" role="alert" hidden></p>
    <form id="cq-supplement" class="cq-supplement" method="POST" action="{{ route('admin.submissions.verification', ['submission' => $selected, ...$actionContext]) }}" data-cq-action>
        @csrf<input type="hidden" name="action" value="supplement"><input type="hidden" name="verification_version" value="{{ app(\App\Services\SubmissionVerification::class)->version($selected, $selectedState) }}">
        <label for="cq-supplement-reason">Lý do</label><select id="cq-supplement-reason" name="supplement_reason" required @disabled(!$pending)><option value=""></option>@foreach (\App\Services\SubmissionVerification::REASONS as $value => $label)<option value="{{ $value }}" @selected($selectedState?->supplement_reason === $value)>{{ $label }}</option>@endforeach<option disabled>Biển số không khớp câu trả lời trong group</option></select>
        <textarea disabled rows="2" placeholder="Ghi chú thêm cho khách / sales (tuỳ chọn)" aria-label="Ghi chú thêm cho khách / sales (tuỳ chọn)"></textarea>
        <footer><p><img src="{{ asset('admin/icons/c02-info.png') }}" width="14" height="14" alt="">Khách xem được lý do này ở trang tra cứu.</p><x-admin.button variant="secondary" size="38" data-cq-supplement-cancel>Huỷ</x-admin.button><x-admin.button :disabled="!$pending" variant="warning" size="36" type="submit">Gửi yêu cầu</x-admin.button></footer>
    </form>
</aside>
