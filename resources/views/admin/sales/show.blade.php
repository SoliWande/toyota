<x-layout :title="'Sales · '.$sales->name">
    <a href="{{ route('admin.sales.index') }}" class="text-sm font-medium text-slate-500 hover:text-red-600">← Danh sách Sales</a>
    <div class="mb-7 mt-4 flex flex-wrap items-center gap-4">
        <h1 class="text-3xl font-bold">{{ $sales->name }}</h1>
        <x-user-status :status="$sales->status" />
    </div>
    <x-admin-feedback />
    <a href="{{ route('admin.sales.edit', $sales) }}" class="button-primary mb-6 inline-flex">Chỉnh sửa thông tin / Chuyển đại lý</a>
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Thông tin tài khoản</h2>
            <dl class="mt-5 space-y-4 text-sm">
                <div><dt class="text-slate-500">Email</dt><dd class="mt-1 break-all">{{ $sales->email }}</dd></div>
                <div><dt class="text-slate-500">Điện thoại</dt><dd class="mt-1">{{ $sales->phone ?? 'Chưa có' }}</dd></div>
                <div><dt class="text-slate-500">Đại lý</dt><dd class="mt-1">{{ $sales->dealer->name }}</dd></div>
                <div><dt class="text-slate-500">Đăng ký lúc</dt><dd class="mt-1">{{ $sales->created_at->format('d/m/Y H:i') }}</dd></div>
                @if ($sales->reviewed_at)
                    <div><dt class="text-slate-500">Review gần nhất</dt><dd class="mt-1">{{ $sales->reviewer->name }} · {{ $sales->reviewed_at->format('d/m/Y H:i') }}</dd></div>
                @endif
                @if ($sales->rejection_reason)<div><dt class="text-slate-500">Lý do từ chối</dt><dd class="mt-1 whitespace-pre-line">{{ $sales->rejection_reason }}</dd></div>@endif
                @if ($sales->admin_note)<div><dt class="text-slate-500">Ghi chú admin</dt><dd class="mt-1 whitespace-pre-line">{{ $sales->admin_note }}</dd></div>@endif
            </dl>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Xét duyệt tài khoản</h2>
            @if ($sales->status === \App\Enums\UserStatus::Pending)
                <form method="POST" action="{{ route('admin.sales.review', [$sales, 'approve']) }}" class="mt-5">
                    @csrf
                    <button type="submit" class="button-primary w-full">Duyệt tài khoản</button>
                </form>
                <form method="POST" action="{{ route('admin.sales.review', [$sales, 'reject']) }}" class="mt-6 border-t border-slate-100 pt-5">
                    @csrf
                    <label for="rejection_reason" class="text-sm font-medium">Lý do từ chối (tùy chọn)</label>
                    <textarea id="rejection_reason" name="rejection_reason" class="form-input" maxlength="2000" rows="3">{{ old('rejection_reason') }}</textarea>
                    <label for="reject_note" class="mt-4 block text-sm font-medium">Ghi chú nội bộ (tùy chọn)</label>
                    <textarea id="reject_note" name="admin_note" class="form-input" maxlength="2000" rows="2">{{ old('admin_note') }}</textarea>
                    <button type="submit" class="mt-4 rounded-xl border border-red-200 px-5 py-3 font-semibold text-red-700 hover:bg-red-50">Từ chối</button>
                </form>
            @elseif ($sales->status === \App\Enums\UserStatus::Active)
                <form method="POST" action="{{ route('admin.sales.review', [$sales, 'block']) }}" class="mt-5" onsubmit="return confirm('Khóa tài khoản Sales này?')">
                    @csrf
                    <label for="block_note" class="text-sm font-medium">Ghi chú nội bộ (tùy chọn)</label>
                    <textarea id="block_note" name="admin_note" class="form-input" maxlength="2000" rows="3">{{ old('admin_note') }}</textarea>
                    <button type="submit" class="mt-4 rounded-xl border border-red-200 px-5 py-3 font-semibold text-red-700 hover:bg-red-50">Khóa tài khoản</button>
                </form>
            @elseif (in_array($sales->status, [\App\Enums\UserStatus::Blocked, \App\Enums\UserStatus::Rejected], true))
                <form method="POST" action="{{ route('admin.sales.review', [$sales, 'reactivate']) }}" class="mt-5" onsubmit="return confirm('Kích hoạt lại tài khoản Sales này?')">
                    @csrf
                    <label for="reactivate_note" class="text-sm font-medium">Ghi chú nội bộ (tùy chọn)</label>
                    <textarea id="reactivate_note" name="admin_note" class="form-input" maxlength="2000" rows="3">{{ old('admin_note') }}</textarea>
                    <button type="submit" class="button-primary mt-4">Kích hoạt lại</button>
                </form>
            @endif
        </section>
    </div>
    <section class="mt-8">
        <h2 class="mb-4 text-xl font-semibold">Lịch sử duyệt</h2>
        <div class="space-y-3">
            @forelse ($reviews as $review)
                <article class="rounded-xl border border-slate-200 bg-white p-5 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-semibold">{{ \App\Enums\UserStatus::from($review->from_status)->label() }} → {{ \App\Enums\UserStatus::from($review->to_status)->label() }}</p>
                        <p class="text-slate-500">{{ $review->reviewer->name }} · {{ $review->reviewed_at->format('d/m/Y H:i') }}</p>
                    </div>
                    @if ($review->rejection_reason)<p class="mt-3 whitespace-pre-line text-red-800">{{ $review->rejection_reason }}</p>@endif
                    @if ($review->admin_note)<p class="mt-2 whitespace-pre-line text-slate-600">{{ $review->admin_note }}</p>@endif
                </article>
            @empty
                <p class="text-sm text-slate-500">Chưa có lịch sử duyệt.</p>
            @endforelse
        </div>
        <div class="mt-5">{{ $reviews->links() }}</div>
    </section>
</x-layout>
