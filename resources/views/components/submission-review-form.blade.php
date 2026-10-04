@props(['submission', 'duplicate' => false, 'queue' => false, 'filters' => []])
@php
    $restoreInput = (string) old('review_submission_id') === (string) $submission->id;
    $parameters = ['submission' => $submission, ...$filters];
@endphp
<form method="POST" action="{{ route('admin.submissions.approve', $parameters) }}" class="space-y-4"
    onsubmit="return confirm(event.submitter && event.submitter.dataset.confirm ? event.submitter.dataset.confirm : 'Xác nhận review khai báo này?')">
    @csrf
    <input type="hidden" name="review_submission_id" value="{{ $submission->id }}">
    @if ($queue)<input type="hidden" name="return_to_queue" value="1">@endif
    <div>
        <label for="reason-{{ $submission->id }}" class="text-sm font-medium">Lý do từ chối (tùy chọn)</label>
        <textarea id="reason-{{ $submission->id }}" name="rejection_reason" rows="2" maxlength="2000" class="form-input">{{ $restoreInput ? old('rejection_reason') : '' }}</textarea>
        <p class="mt-1 text-xs text-slate-500">Chỉ lưu khi chọn Từ chối. Sales có thể xem lý do này.</p>
    </div>
    <div>
        <label for="admin-note-{{ $submission->id }}" class="text-sm font-medium">Ghi chú admin (tùy chọn)</label>
        <textarea id="admin-note-{{ $submission->id }}" name="admin_note" rows="2" maxlength="2000" class="form-input">{{ $restoreInput ? old('admin_note') : '' }}</textarea>
        <p class="mt-1 text-xs text-slate-500">Ghi chú nội bộ, chỉ admin được xem.</p>
    </div>
    <div class="flex flex-col gap-3 sm:flex-row">
        <button type="submit" class="button-primary" data-confirm="Duyệt khai báo này sau khi đã xác minh thành viên Facebook?" @disabled($duplicate)>Duyệt khai báo</button>
        <button type="submit" formaction="{{ route('admin.submissions.reject', $parameters) }}" data-confirm="Từ chối khai báo này? Sales sẽ thấy trạng thái và lý do từ chối." class="min-h-12 rounded-xl border border-red-300 px-5 py-3 font-semibold text-red-700 hover:bg-red-50">Từ chối</button>
    </div>
</form>
