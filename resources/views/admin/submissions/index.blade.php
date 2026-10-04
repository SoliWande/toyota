<x-layout title="Duyệt khai báo">
    <a href="{{ route('admin.dashboard') }}" class="text-sm text-slate-500 hover:text-red-600">← Quản trị</a>
    <h1 class="mt-3 text-3xl font-bold">Duyệt khai báo</h1>
    <p class="mt-2 text-sm text-slate-600">{{ $submissions->total() }} khai báo phù hợp · Ưu tiên khai báo cũ nhất.</p>
    <x-admin-feedback />
    <form method="GET" action="{{ route('admin.submissions.index') }}" class="my-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <label for="q" class="text-sm font-medium">Tìm khách hàng</label>
            <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="255" placeholder="Tên, điện thoại hoặc Facebook" class="form-input">
        </div>
        <div>
            <label for="dealer_id" class="text-sm font-medium">Đại Lý</label>
            <select id="dealer_id" name="dealer_id" class="form-input">
                <option value="">Tất cả Đại Lý</option>
                @foreach ($dealers as $dealer)
                    <option value="{{ $dealer->id }}" @selected((string) ($filters['dealer_id'] ?? '') === (string) $dealer->id)>{{ $dealer->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="sales_id" class="text-sm font-medium">Sales</label>
            <select id="sales_id" name="sales_id" class="form-input">
                <option value="">Tất cả Sales</option>
                @foreach ($salesOptions as $sales)
                    <option value="{{ $sales->id }}" @selected((string) ($filters['sales_id'] ?? '') === (string) $sales->id)>{{ $sales->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="text-sm font-medium">Trạng thái</label>
            <select id="status" name="status" class="form-input">
                <option value="" @selected($status === '')>Tất cả trạng thái</option>
                @foreach (\App\Enums\SubmissionStatus::cases() as $state)
                    <option value="{{ $state->value }}" @selected($status === $state->value)>{{ $state->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="submitted_from" class="text-sm font-medium">Từ ngày khai báo</label>
            <input type="date" id="submitted_from" name="submitted_from" value="{{ $filters['submitted_from'] ?? '' }}" class="form-input">
        </div>
        <div>
            <label for="submitted_to" class="text-sm font-medium">Đến ngày khai báo</label>
            <input type="date" id="submitted_to" name="submitted_to" value="{{ $filters['submitted_to'] ?? '' }}" class="form-input">
        </div>
        <div class="flex flex-wrap items-center gap-4 sm:col-span-2 lg:col-span-3">
            <button type="submit" class="button-primary">Lọc khai báo</button>
            <a href="{{ route('admin.submissions.index') }}" class="py-3 text-sm text-slate-600 hover:underline">Bỏ lọc</a>
            <p class="text-xs text-slate-500">Ngày khai báo theo múi giờ {{ config('app.timezone') }}.</p>
        </div>
    </form>
    <ul class="space-y-3">
        @forelse ($submissions as $submission)
            <li class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div class="min-w-0">
                        <a href="{{ route('admin.submissions.show', $submission) }}" class="break-words font-semibold hover:text-red-600">{{ $submission->customer_name }}</a>
                        <p class="mt-2 break-words text-sm text-slate-500">{{ $submission->sales->name }} · {{ $submission->dealer?->name ?? 'Chưa xác minh đại lý lịch sử' }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $submission->submitted_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <x-submission-status :status="$submission->status" />
                        <a href="{{ $submission->facebook_url_normalized }}" target="_blank" rel="noopener noreferrer" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold hover:bg-slate-50">Mở Facebook</a>
                        <a href="{{ route('admin.submissions.show', $submission) }}" class="py-2 text-sm font-medium text-red-600">Chi tiết</a>
                    </div>
                </div>
                @if ($submission->status === \App\Enums\SubmissionStatus::Pending)
                    @if ($submission->duplicate_approved_id)
                        <p role="alert" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-800">Facebook profile đã được duyệt. <a href="{{ route('admin.submissions.show', $submission->duplicate_approved_id) }}" class="font-semibold underline">Xem record #{{ $submission->duplicate_approved_id }}</a></p>
                    @endif
                    <details class="mt-4 border-t border-slate-100 pt-3" @if ((string) old('review_submission_id') === (string) $submission->id) open @endif>
                        <summary class="min-h-12 cursor-pointer py-3 text-sm font-semibold text-slate-800">Kiểm tra và duyệt nhanh</summary>
                        <dl class="mb-5 space-y-3 text-sm">
                            @if ($submission->phone)<div><dt class="text-slate-500">Điện thoại</dt><dd>{{ $submission->phone }}</dd></div>@endif
                            <div><dt class="text-slate-500">Facebook URL</dt><dd class="break-all">{{ $submission->facebook_url }}</dd></div>
                            <div><dt class="text-slate-500">Ghi chú Sales</dt><dd class="whitespace-pre-wrap break-words">{{ $submission->notes ?? 'Không có' }}</dd></div>
                        </dl>
                        <x-submission-vehicle-details :submission="$submission" />
                        <x-submission-review-form :submission="$submission" :duplicate="(bool) $submission->duplicate_approved_id" :queue="true" :filters="$filters" />
                    </details>
                @endif
            </li>
        @empty
            <li class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-slate-500">Không có khai báo phù hợp.</li>
        @endforelse
    </ul>
    <div class="mt-6">{{ $submissions->links() }}</div>
</x-layout>
