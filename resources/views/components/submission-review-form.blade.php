@props(['submission', 'duplicate' => false, 'queue' => false, 'filters' => []])
@php
    $restoreInput = is_scalar(old('review_submission_id')) && (string) old('review_submission_id') === (string) $submission->id;
    $oldText = fn ($field) => $restoreInput && is_string(old($field)) ? old($field) : '';
    $parameters = ['submission' => $submission, ...$filters];
@endphp
<form method="POST" action="{{ route('admin.submissions.approve', $parameters) }}" class="space-y-4" data-prevent-double-submit>
    @csrf
    @if ($submission->source === 'dealer_qr')
        <div class="rounded-xl bg-amber-50 p-4">
            <label for="facebook-{{ $submission->id }}" class="text-sm font-semibold">Facebook cá nhân đã được Admin xác minh (bắt buộc khi duyệt đăng ký QR)</label>
            <input id="facebook-{{ $submission->id }}" name="verified_facebook_url" type="url" maxlength="2048" value="{{ $oldText('verified_facebook_url') }}" class="form-input" autocomplete="off">
            <p class="mt-2 text-xs leading-5">Kiểm tra danh tính và thành viên Facebook trước khi duyệt. Đăng ký QR không cộng điểm cho Sales.</p>
        </div>
    @endif
    <input type="hidden" name="review_submission_id" value="{{ $submission->id }}">
    @if ($queue)<input type="hidden" name="return_to_queue" value="1">@endif
    <div>
        <label for="reason-{{ $submission->id }}" class="text-sm font-medium">Lý do từ chối (tùy chọn)</label>
        <textarea id="reason-{{ $submission->id }}" name="rejection_reason" rows="2" maxlength="2000" class="form-input">{{ $oldText('rejection_reason') }}</textarea>
        <p class="mt-1 text-xs text-slate-500">Chỉ lưu khi chọn Từ chối. Sales có thể xem lý do này.</p>
    </div>
    <div>
        <label for="admin-note-{{ $submission->id }}" class="text-sm font-medium">Ghi chú admin (tùy chọn)</label>
        <textarea id="admin-note-{{ $submission->id }}" name="admin_note" rows="2" maxlength="2000" class="form-input">{{ $oldText('admin_note') }}</textarea>
        <p class="mt-1 text-xs text-slate-500">Ghi chú nội bộ, chỉ admin được xem.</p>
    </div>
    <div class="flex flex-col gap-3 sm:flex-row">
        <details data-inline-confirmation class="min-w-0 flex-1 rounded-xl border border-slate-200 p-3">
            <summary class="min-h-12 cursor-pointer py-3 font-semibold text-red-700">Duyệt khai báo</summary>
            <p class="my-3 break-words text-sm">Bạn có chắc muốn duyệt khai báo của {{ $submission->customer_name }} thuộc {{ $submission->dealer?->name ?? 'đại lý chưa xác minh' }}?</p>
            <div class="flex flex-wrap gap-3"><button type="submit" class="button-primary" @disabled($duplicate)>Xác nhận duyệt</button><button type="button" data-cancel-inline class="rounded-xl border border-slate-300 px-4 py-3">Hủy</button></div>
        </details>
        <details data-inline-confirmation class="min-w-0 flex-1 rounded-xl border border-slate-200 p-3">
            <summary class="min-h-12 cursor-pointer py-3 font-semibold text-red-700">Từ chối</summary>
            <p class="my-3 text-sm">Xác nhận từ chối khai báo này? Trạng thái và lý do từ chối sẽ được hiển thị theo quyền.</p>
            <div class="flex flex-wrap gap-3"><button type="submit" formaction="{{ route('admin.submissions.reject', $parameters) }}" class="rounded-xl border border-red-300 px-5 py-3 font-semibold text-red-700">Xác nhận từ chối</button><button type="button" data-cancel-inline class="rounded-xl border border-slate-300 px-4 py-3">Hủy</button></div>
        </details>
    </div>
    <p data-submitting-status role="status" aria-live="polite" class="text-sm text-slate-600"></p>
</form>
