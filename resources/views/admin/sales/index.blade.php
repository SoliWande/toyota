<x-layout title="Quản lý Sales">
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('admin.dashboard') }}" class="text-sm font-medium text-slate-500 hover:text-red-600">← Quản trị</a>
            <h1 class="mt-3 text-3xl font-bold">Quản lý Sales</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $sales->total() }} tài khoản phù hợp bộ lọc.</p>
        </div>
    </div>
    <x-admin-feedback />
    <form method="GET" action="{{ route('admin.sales.index') }}" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="q" class="text-sm font-medium">Tìm kiếm</label>
            <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="255" placeholder="Tên, email hoặc điện thoại" class="form-input">
        </div>
        <div>
            <label for="status" class="text-sm font-medium">Trạng thái</label>
            <select id="status" name="status" class="form-input">
                <option value="">Tất cả trạng thái</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="dealer_id" class="text-sm font-medium">Đại lý</label>
            <select id="dealer_id" name="dealer_id" class="form-input">
                <option value="">Tất cả đại lý</option>
                @foreach ($dealers as $dealer)
                    <option value="{{ $dealer->id }}" @selected((string) ($filters['dealer_id'] ?? '') === (string) $dealer->id)>{{ $dealer->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-4">
            <button type="submit" class="button-primary">Lọc</button>
            <a href="{{ route('admin.sales.index') }}" class="py-3 text-sm text-slate-600 hover:underline">Bỏ lọc</a>
        </div>
    </form>
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-100 text-slate-600">
                <tr><th scope="col" class="p-4">Sales</th><th scope="col" class="p-4">Đại lý</th><th scope="col" class="p-4">Trạng thái</th><th scope="col" class="p-4">Thao tác</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sales as $account)
                    <tr>
                        <td class="p-4"><a href="{{ route('admin.sales.show', $account) }}" class="font-semibold hover:text-red-600">{{ $account->name }}</a><p class="mt-1 text-slate-500">{{ $account->email }}</p>@if ($account->phone)<p class="mt-1 text-slate-500">{{ $account->phone }}</p>@endif</td>
                        <td class="p-4">{{ $account->dealer->name }}</td>
                        <td class="whitespace-nowrap p-4"><x-user-status :status="$account->status" /></td>
                        <td class="p-4">
                            <div class="flex items-center gap-4 whitespace-nowrap">
                                <a href="{{ route('admin.sales.show', $account) }}" class="py-2 font-medium text-red-600 hover:underline">Chi tiết</a>
                                @if ($account->status === \App\Enums\UserStatus::Pending)
                                    <form method="POST" action="{{ route('admin.sales.review', [$account, 'approve']) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg bg-green-50 px-3 py-2 font-semibold text-green-800 hover:bg-green-100">Duyệt</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-8 text-center text-slate-500">Không tìm thấy tài khoản Sales phù hợp.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $sales->links() }}</div>
</x-layout>
