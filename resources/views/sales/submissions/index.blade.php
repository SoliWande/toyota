<x-layout title="Khai báo của tôi">
    <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('sales.dashboard') }}" class="text-sm text-slate-500 hover:text-red-600">← Dashboard</a>
            <h1 class="mt-3 text-3xl font-bold">Khai báo của tôi</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $submissions->total() }} khai báo phù hợp.</p>
        </div>
        <a href="{{ route('sales.submissions.create') }}" class="button-primary">Thêm khách hàng</a>
    </div>
    <x-admin-feedback />
    <form method="GET" action="{{ route('sales.submissions.index') }}" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-[1fr_12rem_auto] sm:items-end">
        <div class="min-w-0">
            <label for="q" class="text-sm font-medium">Tìm khách hàng</label>
            <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="255" placeholder="Tên, Facebook hoặc điện thoại" class="form-input">
        </div>
        <div>
            <label for="status" class="text-sm font-medium">Trạng thái</label>
            <select id="status" name="status" class="form-input">
                <option value="">Tất cả</option>
                @foreach (\App\Enums\SubmissionStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-4">
            <button type="submit" class="button-primary">Tìm kiếm</button>
            <a href="{{ route('sales.submissions.index') }}" class="shrink-0 text-sm text-slate-600 hover:underline">Bỏ lọc</a>
        </div>
    </form>
    <ul class="space-y-3">
        @forelse ($submissions as $submission)
            <li class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <a href="{{ route('sales.submissions.show', $submission) }}" class="break-words font-semibold hover:text-red-600">{{ $submission->customer_name }}</a>
                    <p class="mt-2 text-sm text-slate-500">{{ $submission->submitted_at->format('d/m/Y H:i') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <x-submission-status :status="$submission->status" />
                    <a href="{{ route('sales.submissions.show', $submission) }}" class="py-2 text-sm font-medium text-red-600">Xem chi tiết</a>
                    @can('update', $submission)
                        <a href="{{ route('sales.submissions.edit', $submission) }}" class="py-2 text-sm text-slate-600">Sửa</a>
                    @endcan
                </div>
            </li>
        @empty
            <li class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">Không có khai báo phù hợp.</li>
        @endforelse
    </ul>
    <div class="mt-6">{{ $submissions->links() }}</div>
</x-layout>
