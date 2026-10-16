<x-layout title="Dashboard đại lý">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-bold uppercase tracking-widest text-red-600">Không gian đại lý</p>
            <h1 class="mt-3 break-words text-2xl font-bold sm:text-3xl">Xin chào, {{ $account->name }}</h1>
            <p class="mt-2 break-words text-sm text-slate-600">{{ $account->dealer->name }} · {{ $account->dealer->code }}</p>
        </div>
        <div class="flex flex-wrap gap-3 sm:shrink-0">
            <a href="{{ route('dealer.submissions.create') }}" class="button-primary">Tạo khai báo</a>
            <a href="{{ route('profile.edit') }}" class="inline-flex min-h-12 items-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold hover:border-red-400">Hồ sơ</a>
        </div>
    </div>

    <dl class="mt-7 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach (['total' => 'Tổng khai báo', 'pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối'] as $key => $label)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-6">
                <dt class="text-sm font-medium text-slate-600">{{ $label }}</dt>
                <dd class="mt-3 text-3xl font-bold {{ $key === 'approved' ? 'text-green-700' : 'text-slate-900' }}">{{ number_format($stats[$key], 0, ',', '.') }}</dd>
            </div>
        @endforeach
    </dl>

    <section aria-labelledby="ranking-heading" class="mt-7">
        <h2 id="ranking-heading" class="text-xl font-bold">Thành tích đại lý</h2>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            @foreach (\App\Enums\LeaderboardPeriod::cases() as $period)
                @php($standing = $standings[$period->value])
                <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h3 class="font-semibold">{{ $period->label() }}</h3>
                    <p class="mt-3 text-3xl font-bold text-red-600">{{ number_format($standing?->score ?? 0, 0, ',', '.') }} <span class="text-sm font-normal text-slate-500">điểm</span></p>
                    <p class="mt-2 text-sm text-slate-600">{{ $standing ? 'Thứ hạng #'.$standing->rank : 'Chưa có dữ liệu xếp hạng.' }}</p>
                    <a href="{{ route('leaderboard', ['period' => $period->value]) }}" class="mt-4 inline-block py-2 text-sm font-semibold text-red-600 hover:underline">Xem bảng xếp hạng</a>
                </div>
            @endforeach
        </div>
        <p class="mt-3 text-xs leading-5 text-slate-500">Mỗi khai báo đã duyệt được tính một điểm cho đại lý tại thời điểm khai báo. Tuần/tháng tính theo ngày khai báo · Múi giờ {{ config('app.timezone') }}.</p>
    </section>

    <section aria-labelledby="recent-heading" class="mt-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="recent-heading" class="text-xl font-bold">Khai báo gần đây</h2>
            <a href="{{ route('dealer.submissions.index') }}" class="py-2 text-sm font-medium text-red-600 hover:underline">Xem tất cả</a>
        </div>
        <p class="mt-1 text-sm text-slate-500">5 khai báo mới nhất của đại lý.</p>
        <ul class="mt-4 space-y-3">
            @forelse ($recentSubmissions as $submission)
                <li class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <a href="{{ route('dealer.submissions.show', $submission) }}" class="break-words font-semibold hover:text-red-600">{{ $submission->customer_name }}</a>
                        <p class="mt-1 text-sm text-slate-500">Khai báo lúc <time datetime="{{ $submission->submitted_at->toIso8601String() }}">{{ $submission->submitted_at->format('d/m/Y H:i') }}</time></p>
                    </div>
                    <x-submission-status :status="$submission->status" class="self-start sm:shrink-0" />
                </li>
            @empty
                <li class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center">
                    <p class="font-semibold">Đại lý chưa có khai báo nào</p>
                    <p class="mt-2 text-sm text-slate-500">Tạo khai báo đầu tiên để theo dõi trạng thái duyệt và thành tích đại lý.</p>
                </li>
            @endforelse
        </ul>
    </section>
</x-layout>
