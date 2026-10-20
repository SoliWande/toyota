<x-layout title="Admin Dashboard">
    <p class="text-xs font-bold uppercase tracking-widest text-red-600">Không gian quản trị</p>
    <h1 class="mt-3 text-3xl font-bold break-words">Xin chào, {{ auth()->user()->name }}</h1>
    <p class="mt-3 text-slate-600">Theo dõi chương trình và xử lý các khai báo đang chờ duyệt.</p>

    <section aria-labelledby="moderation-title" class="mt-8 rounded-2xl border border-red-200 bg-white p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="moderation-title" class="text-lg font-bold">Cần xử lý</h2>
            <p class="rounded-full bg-red-50 px-4 py-2 text-sm font-semibold text-red-700">{{ number_format($stats['pending_sales'] + $stats['pending_submissions']) }} mục chờ duyệt</p>
        </div>
        @if ($stats['pending_sales'] + $stats['pending_submissions'] === 0)
            <p class="mt-4 text-sm text-slate-600">Đã xử lý hết hàng chờ. Hiện không có tài khoản hoặc khai báo cần duyệt.</p>
        @endif
        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <a href="{{ route('admin.sales.index', ['status' => 'pending']) }}" class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 p-4 hover:border-red-400 focus-visible:outline-red-600">
                <span><span class="block font-semibold">Duyệt Sales</span><span class="mt-1 block text-sm text-slate-500">Tài khoản đăng ký mới</span></span>
                <span class="text-2xl font-bold text-red-600">{{ number_format($stats['pending_sales']) }}</span>
            </a>
            <a href="{{ route('admin.submissions.index', ['status' => 'pending']) }}" class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 p-4 hover:border-red-400 focus-visible:outline-red-600">
                <span><span class="block font-semibold">Duyệt khách hàng</span><span class="mt-1 block text-sm text-slate-500">Xác minh thành viên Facebook</span></span>
                <span class="text-2xl font-bold text-red-600">{{ number_format($stats['pending_submissions']) }}</span>
            </a>
        </div>
        <nav aria-label="Thao tác nhanh" class="mt-5 flex flex-wrap gap-3 text-sm font-semibold">
            <a href="{{ route('admin.dealers.index') }}" class="rounded-lg bg-slate-100 px-4 py-3 hover:bg-slate-200 focus-visible:outline-red-600">Quản lý Đại Lý</a>
            <a href="{{ route('leaderboard') }}" class="rounded-lg bg-slate-100 px-4 py-3 hover:bg-slate-200 focus-visible:outline-red-600">Xem Leaderboard</a>
            <a href="{{ route('admin.awards.index') }}" class="rounded-lg bg-slate-100 px-4 py-3 hover:bg-slate-200 focus-visible:outline-red-600">Tạo vinh danh</a>
        </nav>
    </section>

    <section aria-labelledby="stats-title" class="mt-8">
        <h2 id="stats-title" class="text-lg font-bold">Tổng quan chương trình</h2>
        <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ([
                'dealers' => 'Tổng Đại Lý',
                'active_dealers' => 'Đại Lý đang hoạt động',
                'sales' => 'Tổng Sales',
                'submissions' => 'Tổng khách hàng',
                'active_sales' => 'Sales đang hoạt động',
                'pending_sales' => 'Sales chờ duyệt',
                'approved_submissions' => 'Khách hàng đã duyệt',
                'pending_submissions' => 'Khai báo chờ duyệt',
                'week_submissions' => 'Khai báo tuần này',
                'month_submissions' => 'Khai báo tháng này',
            ] as $key => $label)
                <div class="relative rounded-xl border border-slate-200 bg-white p-4 hover:border-red-400 sm:p-5">
                    <dt class="text-sm text-slate-600">{{ $label }}</dt>
                    <dd class="mt-3 text-3xl font-bold tabular-nums text-red-700">
                        <a href="{{ $statLinks[$key] }}" data-stat="{{ $key }}" aria-label="Xem danh sách {{ $label }}" class="rounded focus-visible:outline-red-600"><span class="absolute inset-0 rounded-xl" aria-hidden="true"></span>{{ number_format($stats[$key]) }}</a>
                    </dd>
                </div>
            @endforeach
        </dl>
        <p class="mt-3 text-xs leading-5 text-slate-500">Khai báo tuần/tháng gồm mọi trạng thái, tính theo thời điểm khai báo (submitted_at). Múi giờ: {{ config('app.timezone') }}.</p>
    </section>

    <section aria-labelledby="pending-title" class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="pending-title" class="text-lg font-bold">Khai báo chờ duyệt gần đây</h2>
            <a href="{{ route('admin.submissions.index', ['status' => 'pending']) }}" class="py-2 text-sm font-semibold text-red-600 hover:underline">Xem toàn bộ hàng chờ →</a>
        </div>
        <ul class="mt-3 divide-y divide-slate-100">
            @forelse ($recentPendingSubmissions as $submission)
                <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="font-semibold break-words"><a href="{{ route('admin.submissions.show', $submission) }}" class="rounded hover:text-red-700 hover:underline focus-visible:outline-red-600">{{ $submission->customer_name }}</a></p>
                        <p class="mt-1 text-sm text-slate-600 break-words"><x-sales-link :sales="$submission->sales" /> · <x-dealer-link :dealer-id="$submission->dealer_id" :name="$submission->dealer?->name ?? 'Chưa xác minh đại lý lịch sử'" /></p>
                        <p class="mt-1 text-xs text-slate-500">Khai báo {{ $submission->submitted_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <a href="{{ route('admin.submissions.show', $submission) }}" aria-label="Xem và duyệt {{ $submission->customer_name }}" class="shrink-0 self-start rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 hover:bg-red-100 focus-visible:outline-red-600">Xem và duyệt →</a>
                </li>
            @empty
                <li class="py-5 text-sm text-slate-500">Không có khai báo chờ duyệt.</li>
            @endforelse
        </ul>
    </section>

    <section aria-labelledby="ranking-title" class="mt-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 id="ranking-title" class="text-lg font-bold">Dẫn đầu tháng này</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $monthStart->format('d/m/Y') }} – {{ $monthEnd->subDay()->format('d/m/Y') }} · Chỉ tính khai báo đã duyệt theo submitted_at.</p>
            </div>
            <a href="{{ route('leaderboard', ['period' => 'month']) }}" class="py-2 text-sm font-semibold text-red-600 hover:underline">Xem bảng xếp hạng →</a>
        </div>
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <x-public.ranking-preview title="Top 3 Sales" :leaders="$topSales" type="sales" detail-route="admin.sales.show" :avatars="$salesAvatars" :dealer-ids="$salesDealerIds" :public-slugs="$salesPublicSlugs" />
            <x-public.ranking-preview title="Top 3 Đại Lý" :leaders="$topDealers" type="dealers" detail-route="admin.dealers.show" />
        </div>
    </section>
</x-layout>
