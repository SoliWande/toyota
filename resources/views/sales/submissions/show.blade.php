<x-layout title="Chi tiết khai báo">
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('sales.submissions.index') }}" class="text-sm text-slate-500 hover:text-red-600">← Khai báo của tôi</a>
        <h1 class="mb-6 mt-4 break-words text-3xl font-bold">{{ $submission->customer_name }}</h1>
        <x-admin-feedback />
        <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-8">
            <x-submission-status :status="$submission->status" />
            <dl class="mt-6 space-y-5 text-sm">
                <div><dt class="text-slate-500">Facebook</dt><dd class="mt-1 break-all"><a href="{{ $submission->facebook_url_normalized }}" target="_blank" rel="noopener noreferrer" class="font-medium text-red-600 hover:underline">{{ $submission->facebook_url }}</a></dd></div>
                <div><dt class="text-slate-500">Số điện thoại</dt><dd class="mt-1">{{ $submission->phone ?? 'Chưa cung cấp' }}</dd></div>
                <div><dt class="text-slate-500">Ghi chú</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $submission->notes ?? 'Không có' }}</dd></div>
                <div><dt class="text-slate-500">Thời điểm khai báo</dt><dd class="mt-1">{{ $submission->submitted_at->format('d/m/Y H:i') }}</dd></div>
                @if ($submission->reviewed_at)
                    <div><dt class="text-slate-500">Thời điểm duyệt</dt><dd class="mt-1">{{ $submission->reviewed_at->format('d/m/Y H:i') }}</dd></div>
                @endif
                @if ($submission->status === \App\Enums\SubmissionStatus::Rejected && $submission->rejection_reason)
                    <div><dt class="text-slate-500">Lý do từ chối</dt><dd class="mt-1 whitespace-pre-wrap break-words text-red-700">{{ $submission->rejection_reason }}</dd></div>
                @endif
            </dl>
            <x-submission-vehicle-details :submission="$submission" />
            @can('update', $submission)
                <div class="mt-7 flex flex-wrap items-center gap-4 border-t border-slate-100 pt-5">
                    <a href="{{ route('sales.submissions.edit', $submission) }}" class="button-primary">Sửa khai báo</a>
                    <form method="POST" action="{{ route('sales.submissions.destroy', $submission) }}" onsubmit="return confirm('Bạn có chắc muốn xóa khai báo này?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="rounded-xl border border-red-200 px-5 py-3 text-sm font-semibold text-red-700">Xóa khai báo</button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-layout>
