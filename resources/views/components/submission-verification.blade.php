@props(['submission', 'state', 'audits'])
@php
    $service = app(\App\Services\SubmissionVerification::class);
    $version = $service->version($submission, $state);
    $bag = $errors->getBag('verification');
    $labels = $service::GROUP;
    $auditSummary = function ($json) use ($service, $labels) {
        $fields = ['group_progress' => 'Group', 'blocker_code' => 'Xác minh', 'supplement_state' => 'Bổ sung', 'supplement_reason' => 'Lý do'];
        $states = ['open' => 'Đang yêu cầu', 'submitted' => 'Đã nhận bổ sung', 'closed' => 'Đã đóng'];
        return collect(json_decode($json, true))->only(array_keys($fields))->map(function ($value, $field) use ($service, $labels, $fields, $states) {
            $options = ['group_progress' => $labels, 'blocker_code' => $service::BLOCKERS, 'supplement_state' => $states, 'supplement_reason' => $service::REASONS][$field];
            return $fields[$field].': '.($options[$value ?? 'unknown'] ?? 'Chưa có dữ liệu');
        })->implode(' · ') ?: 'Chưa có dữ liệu';
    };
@endphp
<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-8" aria-labelledby="verification-heading">
    <h2 id="verification-heading" class="text-xl font-bold">Trạng thái Facebook Group</h2>
    <p class="mt-2 text-sm text-slate-600">Chỉ cập nhật sau khi kiểm tra Group thực tế. Thao tác này không duyệt đăng ký hoặc cộng điểm.</p>
    <p class="mt-3 text-sm">Hiện tại: <strong>{{ $labels[$state?->group_progress ?? 'unknown'] }}</strong></p>
    @if ($state?->group_progress && !$service->evidencedProgress($submission, $state))<p class="mt-2 text-sm text-amber-800">Facebook profile đã thay đổi hoặc bằng chứng không còn khớp. Cần Admin kiểm tra và xác nhận lại; tra cứu chưa sử dụng bằng chứng cũ.</p>@endif
    @if ($state?->group_verified_by)
        <p class="mt-1 text-sm text-slate-500">Người xác minh: {{ \App\Models\User::find($state->group_verified_by)?->name ?? 'Admin' }} · {{ $state->group_verified_at }}</p>
    @endif
    @if ($submission->status->value === 'rejected' && $state?->group_progress)
        <p class="mt-3 text-sm text-amber-800" role="status">Đăng ký đã bị từ chối. Bằng chứng Group được giữ riêng; tra cứu không tuyên bố khách là thành viên chính thức. Hãy kiểm tra và điều chỉnh bằng chứng nếu cần.</p>
    @endif
    @if ($bag->has('verification_version'))<p role="alert" class="mt-3 text-sm text-red-700">{{ $bag->first('verification_version') }}</p>@endif
    @foreach (['group' => 'Xác nhận trạng thái Group', 'supplement' => 'Yêu cầu bổ sung', 'blocker' => 'Lý do chưa thể xác nhận'] as $action => $heading)
        @php($field = ['group' => 'group_progress', 'supplement' => 'supplement_reason', 'blocker' => 'blocker_code'][$action])
        @php($options = ['group' => $labels, 'supplement' => $service::REASONS, 'blocker' => ['' => 'Không có blocker'] + $service::BLOCKERS][$action])
        @php($current = $state?->{$field} ?? ($action === 'group' ? 'unknown' : ''))
        <form method="POST" action="{{ route('admin.submissions.verification', $submission) }}" class="mt-5 border-t border-slate-100 pt-4">
            @csrf
            <input type="hidden" name="verification_version" value="{{ $version }}">
            <input type="hidden" name="action" value="{{ $action }}">
            <label for="verification-{{ $field }}" class="block text-sm font-semibold">{{ $heading }}</label>
            <select id="verification-{{ $field }}" name="{{ $field }}" class="mt-2 w-full rounded-xl border border-slate-300 p-3" aria-invalid="{{ $bag->has($field) ? 'true' : 'false' }}" aria-describedby="verification-{{ $field }}-error">
                @foreach ($options as $value => $label)<option value="{{ $value }}" @selected((old('action') === $action ? old($field, $current) : $current) === $value)>{{ $label }}</option>@endforeach
            </select>
            @if ($action === 'supplement')<p class="mt-2 text-sm text-slate-500">Facebook URL: khách có receipt cùng session còn hạn được cập nhật khi pending. Thiếu thông tin khác: hướng dẫn liên hệ Dealer/Admin, không mở quyền sửa hồ sơ.</p>@endif
            <p id="verification-{{ $field }}-error" class="mt-2 text-sm text-red-700" aria-live="polite">{{ $bag->first($field) }}</p>
            <button type="submit" class="button-primary mt-3">{{ $heading }}</button>
        </form>
    @endforeach
    @if ($state?->supplement_state)
        <p class="mt-5 text-sm">Bổ sung: <strong>{{ ['open' => 'Đang yêu cầu', 'submitted' => 'Đã nhận bổ sung', 'closed' => 'Đã đóng'][$state->supplement_state] }}</strong> · {{ $state->supplement_requested_at }} @if ($state->supplement_responded_at) · Đã nhận: {{ $state->supplement_responded_at }} @endif</p>
        <form method="POST" action="{{ route('admin.submissions.verification', $submission) }}" class="mt-3">@csrf<input type="hidden" name="verification_version" value="{{ $version }}"><input type="hidden" name="action" value="close_supplement"><button class="text-sm font-semibold underline" type="submit">Đóng yêu cầu bổ sung</button></form>
    @endif
    <details class="mt-6"><summary class="cursor-pointer text-sm font-semibold">Lịch sử xác minh gần nhất</summary>
        <ol class="mt-3 space-y-3 text-sm">
            @foreach ($audits as $audit)
                <li class="rounded-xl bg-slate-50 p-3"><p>{{ $audit->created_at }} · {{ $audit->actor_type === 'customer' ? 'Khách hàng đã xác thực' : $audit->actor_name }} · {{ ['group' => 'Xác minh Group', 'supplement' => 'Yêu cầu bổ sung', 'close_supplement' => 'Đóng yêu cầu', 'blocker' => 'Cập nhật lý do xác minh', 'response' => 'Nhận bổ sung'][$audit->action] }}</p>
                    <p class="mt-1 break-words text-slate-600">{{ $auditSummary($audit->old_values) }} → {{ $auditSummary($audit->new_values) }}</p>
                </li>
            @endforeach
        </ol>
    </details>
</section>
