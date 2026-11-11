<x-admin.layout title="Tra SĐT vào group">
    @vite(['resources/css/admin-group-lookup.css', 'resources/js/admin-group-lookup.js'])
    @php
        $error = $errors->first() ?: match ($match['kind']) {
            'not_found' => 'Không tìm thấy hồ sơ khớp số điện thoại.',
            'ambiguous' => 'Số điện thoại khớp nhiều hồ sơ hoặc dữ liệu không nhất quán. Không thể chọn khách để xác minh.',
            default => '',
        };
        $canVerify = $submission && $profileUrl && filled($submission->license_plate) && $progress !== 'member';
        $displayPhone = strlen($phone ?? '') === 10 ? substr($phone, 0, 4).' '.substr($phone, 4, 3).' '.substr($phone, 7) : $phone;
    @endphp
    <div class="gl-page">
        <header class="gl-head"><h1>Tra SĐT vào group</h1><p>Dành cho admin group Facebook: mỗi yêu cầu vào group, dán SĐT khách trả lời để đối chiếu.</p></header>
        <div class="gl-split">
            <div class="gl-illustration" aria-hidden="true">
                <h2>CỬA SỔ FACEBOOK · MINH HOẠ</h2>
                <div class="gl-facebook"><h3>Yêu cầu tham gia nhóm</h3><div class="gl-fb-person"><span>A</span><strong>[Tên Facebook của khách]</strong></div>
                    <div class="gl-fb-question"><small>Câu 1 · Số điện thoại đã đăng ký?</small><p>0901 234 567</p></div>
                    <div class="gl-fb-question"><small>Câu 2 · Biển số xe?</small><p>51K-123.45</p></div>
                    <div class="gl-fb-actions"><span>[Duyệt]</span><span>[Từ chối]</span></div>
                </div>
                <div class="gl-caption"><img src="{{ asset('admin/icons/c03-info-small.png') }}" width="14" height="14" alt=""><p>Admin group mở song song: Facebook bên trái, công cụ tra cứu bên phải.</p></div>
            </div>
            <div class="gl-tools">
                <section class="gl-lookup" aria-label="Tra SĐT khi duyệt vào group" data-lookup-kind="{{ $match['kind'] }}">
                    <h2>Tra SĐT khi duyệt vào group</h2>
                    <form method="POST" action="{{ route('admin.group-lookup.search') }}" class="gl-search" data-group-search>
                        @csrf
                        <img src="{{ asset('admin/icons/c03-search.png') }}" width="22" height="22" alt="">
                        <input type="tel" name="phone" value="{{ $displayPhone }}" required maxlength="30" autocomplete="off" aria-label="Số điện thoại" data-lookup-error="{{ $error }}">
                        <button type="submit"><img src="{{ asset('admin/icons/c03-enter.png') }}" width="14" height="14" alt="">Enter</button>
                    </form>
                    @if ($submission)
                        <form method="POST" action="{{ route('admin.group-lookup.verify') }}" class="gl-result" data-group-verify>
                            @csrf
                            <input type="hidden" name="submission_id" value="{{ $submission->id }}"><input type="hidden" name="verification_version" value="{{ $version }}">
                            <header><span class="gl-avatar">{{ mb_substr(trim($submission->customer_name), 0, 1).mb_substr(\Illuminate\Support\Str::afterLast(trim($submission->customer_name), ' '), 0, 1) }}</span><div><h3>{{ $submission->customer_name }}</h3><p>{{ implode(' · ', array_filter([$submission->dealer?->name, $submission->sales?->role === \App\Enums\UserRole::Sales ? $submission->sales->name : null, $submission->source === 'dealer_qr' ? 'QR đại lý' : null])) }}</p></div>
                                @if ($progress === 'member')<x-admin.badge variant="approved">Đã xác minh</x-admin.badge>
                                @elseif (($state->supplement_state ?? null) === 'open')<x-admin.badge variant="rejected">Cần bổ sung</x-admin.badge>
                                @elseif (in_array($progress, ['requested', 'not_requested'], true))<x-admin.badge variant="info">Chờ vào group</x-admin.badge>@endif
                            </header>
                            <div class="gl-check"><span class="gl-checked"><img src="{{ asset('admin/icons/c03-checked.png') }}" width="12" height="12" alt=""></span><span>SĐT khớp hồ sơ</span></div>
                            <label class="gl-check"><input type="checkbox" name="plate_match" value="1" required @disabled(!$canVerify)><span>Biển số khớp: {{ filled($submission->license_plate) ? substr(\App\Services\SubmissionIntake::plate($submission->license_plate), 0, 3).'-xxx.xx' : '—' }}</span></label>
                            <div class="gl-check"><label><input type="checkbox" name="facebook_match" value="1" required @disabled(!$canVerify)><span>Tên Facebook giống hồ sơ?</span></label>@if ($profileUrl)<a href="{{ $profileUrl }}" target="_blank" rel="noopener noreferrer">Mở FB trong hồ sơ<img src="{{ asset('admin/icons/c03-external.png') }}" width="13" height="13" alt=""></a>@endif</div>
                            <div class="gl-actions"><button type="submit" class="gl-verify" @disabled(!$canVerify)><img src="{{ asset('admin/icons/c03-tick.png') }}" width="16" height="16" alt="">Xác minh thành viên</button><button type="button" class="gl-supplement" disabled>Không khớp · Yêu cầu bổ sung</button></div>
                        </form>
                    @endif
                    <div class="gl-note"><img src="{{ asset('admin/icons/c03-info.png') }}" width="16" height="16" alt=""><p>Xác minh xong thì bấm [Duyệt] bên Facebook. Chưa tìm thấy SĐT thì giữ yêu cầu chờ, khách có thể đang điền form; quá 48 giờ vẫn chưa có hồ sơ thì nhắn khách đăng ký.</p></div>
                </section>
                <section class="gl-recent"><h2>VỪA XỬ LÝ</h2>@foreach ($recent as $row)<div class="gl-recent-row"><strong>{{ $row['name'] }}</strong><x-admin.badge :variant="$row['member'] ? 'approved' : 'rejected'">{{ $row['member'] ? 'Đã xác minh' : 'Cần bổ sung' }}</x-admin.badge><time datetime="{{ \Carbon\CarbonImmutable::parse($row['at'], config('app.timezone'))->toIso8601String() }}"><img src="{{ asset('admin/icons/c03-clock.png') }}" width="13" height="13" alt="">{{ \Carbon\CarbonImmutable::parse($row['at'], config('app.timezone'))->setTimezone('Asia/Ho_Chi_Minh')->format('H:i') }}</time></div>@endforeach</section>
            </div>
        </div>
    </div>
    @if (! $errors->any() && session('group_lookup_success'))<x-admin.toast id="group-lookup-success" class="gl-toast" data-group-success>{{ session('group_lookup_success') }}</x-admin.toast>@endif
</x-admin.layout>
