<x-layout title="Kiểm tra khai báo">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('admin.submissions.index') }}" class="text-sm text-slate-500 hover:text-red-600">← Duyệt khai báo</a>
        <h1 class="mb-6 mt-4 break-words text-3xl font-bold">{{ $submission->customer_name }}</h1>
        <x-admin-feedback />
        @if ($duplicate)
            <div role="alert" class="mb-6 rounded-2xl border border-red-300 bg-red-50 p-5 text-red-900">
                <h2 class="font-bold">Facebook profile đã được duyệt trước đó</h2>
                <p class="mt-2 text-sm leading-6">Không thể ghi nhận thêm thành tích cho profile này, kể cả cùng Sales. Khai báo hiện tại vẫn chờ duyệt.</p>
                <p class="mt-3 break-words text-sm">Sales: <strong>{{ $duplicate->sales->name }}</strong></p>
                <p class="mt-1 break-words text-sm">Đại Lý: <strong>{{ $duplicate->dealer?->name ?? 'Chưa xác minh đại lý lịch sử' }}</strong> ({{ $duplicate->dealer?->code ?? '—' }})</p>
                <a href="{{ route('admin.submissions.show', $duplicate) }}" class="mt-3 inline-block py-2 text-sm font-semibold underline">Xem record đã duyệt #{{ $duplicate->id }}</a>
            </div>
        @endif
        <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-8">
            <x-submission-status :status="$submission->status" />
            <dl class="mt-6 space-y-4 text-sm">
                <div><dt class="text-slate-500">Sales</dt><dd class="mt-1 break-words">{{ $submission->sales->name }}</dd></div>
                <div><dt class="text-slate-500">Đại Lý</dt><dd class="mt-1 break-words">{{ $submission->dealer?->name ?? 'Chưa xác minh đại lý lịch sử' }} · {{ $submission->dealer?->code ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Facebook URL</dt><dd class="mt-1 break-all">{{ $submission->facebook_url }}</dd></div>
                @if ($submission->phone)<div><dt class="text-slate-500">Điện thoại</dt><dd class="mt-1">{{ $submission->phone }}</dd></div>@endif
                <div><dt class="text-slate-500">Ghi chú Sales</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $submission->notes ?? 'Không có' }}</dd></div>
                <div><dt class="text-slate-500">Thời điểm khai báo</dt><dd class="mt-1">{{ $submission->submitted_at->format('d/m/Y H:i') }}</dd></div>
                @if ($submission->reviewed_at)
                    <div><dt class="text-slate-500">Admin đã duyệt / Thời điểm duyệt</dt><dd class="mt-1">{{ $submission->reviewer->name }} · {{ $submission->reviewed_at->format('d/m/Y H:i') }}</dd></div>
                    <div><dt class="text-slate-500">Ghi chú admin</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $submission->admin_note ?? 'Không có' }}</dd></div>
                    @if ($submission->rejection_reason)
                        <div><dt class="text-slate-500">Lý do từ chối</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $submission->rejection_reason }}</dd></div>
                    @endif
                @endif
            </dl>
            <x-submission-vehicle-details :submission="$submission" />
            <a href="{{ $submission->facebook_url_normalized }}" target="_blank" rel="noopener noreferrer" class="button-primary mt-6 w-full sm:w-auto">Mở Facebook</a>
            @if ($submission->status === \App\Enums\SubmissionStatus::Pending)
                <p class="mt-4 text-sm leading-6 text-slate-500">Xác minh thành viên trong Facebook Group trước khi duyệt. Hệ thống kiểm tra lại duplicate khi bạn bấm duyệt.</p>
                <div class="mt-6 border-t border-slate-100 pt-6">
                    <x-submission-review-form :submission="$submission" :duplicate="$duplicate !== null" />
                </div>
            @endif
        </div>
    </div>
</x-layout>
