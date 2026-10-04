<x-layout title="Sales Dashboard">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-bold uppercase tracking-widest text-red-600">Không gian Sales</p>
            <h1 class="mt-3 break-words text-2xl font-bold sm:text-3xl">Xin chào, {{ $sales->name }}</h1>
            <p class="mt-2 break-words text-sm text-slate-600">{{ $sales->dealer->name }} · {{ $sales->dealer->code }}</p>
        </div>
        <div class="shrink-0">
            <a href="{{ route('sales.submissions.create') }}" class="button-primary w-full sm:w-auto">Thêm khách hàng</a>
        </div>
    </div>

    <dl class="mt-7 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach (['total' => 'Tổng submissions', 'approved' => 'Đã duyệt', 'pending' => 'Chờ duyệt', 'rejected' => 'Từ chối'] as $key => $label)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-6">
                <dt class="text-sm font-medium text-slate-600">{{ $label }}</dt>
                <dd class="mt-3 text-3xl font-bold {{ $key === 'approved' ? 'text-green-700' : 'text-slate-900' }}">{{ number_format($stats[$key], 0, ',', '.') }}</dd>
            </div>
        @endforeach
    </dl>

    <section aria-labelledby="ranking-heading" class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        <h2 id="ranking-heading" class="text-lg font-semibold">Thứ hạng toàn chương trình</h2>
        @if ($currentRank !== null)
            <p class="mt-2 text-3xl font-bold text-red-600">#{{ $currentRank }}</p>
        @else
            <p class="mt-2 text-sm text-slate-600">Chưa có dữ liệu xếp hạng.</p>
        @endif
        <p class="mt-2 text-sm text-slate-500">Chỉ khai báo đã được duyệt mới tính vào thành tích xếp hạng.</p>
    </section>

    <section aria-labelledby="recent-heading" class="mt-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="recent-heading" class="text-xl font-bold">Submissions gần đây</h2>
            <a href="{{ route('sales.submissions.index') }}" class="text-sm font-medium text-red-600">Xem tất cả</a>
        </div>
        <p class="mt-1 text-sm text-slate-500">5 khai báo mới nhất của bạn.</p>
        <ul class="mt-4 space-y-3">
            @forelse ($recentSubmissions as $submission)
                <li class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <a href="{{ route('sales.submissions.show', $submission) }}" class="break-words font-semibold hover:text-red-600">{{ $submission->customer_name }}</a>
                        <p class="mt-1 text-sm text-slate-500">Khai báo lúc <time datetime="{{ $submission->submitted_at->toIso8601String() }}">{{ $submission->submitted_at->format('d/m/Y H:i') }}</time></p>
                    </div>
                    <x-submission-status :status="$submission->status" class="self-start sm:shrink-0" />
                </li>
            @empty
                <li class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center">
                    <p class="font-semibold">Bạn chưa có khai báo nào</p>
                    <p class="mt-2 text-sm text-slate-500">Các khách hàng bạn khai báo sẽ xuất hiện tại đây cùng trạng thái duyệt.</p>
                </li>
            @endforelse
        </ul>
    </section>
</x-layout>
