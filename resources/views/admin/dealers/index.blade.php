<x-layout title="Quản lý đại lý">
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('admin.dashboard') }}" class="text-sm text-slate-500 hover:text-red-600">← Quản trị</a>
            <h1 class="mt-3 text-3xl font-bold">Quản lý đại lý</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $dealers->total() }} đại lý phù hợp.</p>
        </div>
        <a href="{{ route('admin.dealers.create') }}" class="button-primary">Thêm đại lý</a>
    </div>
    <x-admin-feedback />
    <form method="GET" action="{{ route('admin.dealers.index') }}" class="mb-6 flex flex-wrap items-end gap-4 rounded-2xl border border-slate-200 bg-white p-5">
        <div class="min-w-0 grow">
            <label for="q" class="text-sm font-medium">Tìm kiếm đại lý</label>
            <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="255" placeholder="Tên, mã, tỉnh/thành phố, điện thoại hoặc địa chỉ" class="form-input">
        </div>
        <button class="button-primary" type="submit">Tìm kiếm</button>
        <a href="{{ route('admin.dealers.index') }}" class="py-3 text-sm text-slate-600 hover:underline">Bỏ lọc</a>
    </form>
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 text-slate-600"><tr><th class="p-4" scope="col">Đại lý</th><th class="p-4" scope="col">Liên hệ</th><th class="p-4" scope="col">Sales</th><th class="p-4" scope="col">Trạng thái</th><th class="p-4" scope="col">Thao tác</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($dealers as $dealer)
                    <tr>
                        <td class="p-4"><a class="font-semibold hover:text-red-600" href="{{ route('admin.dealers.edit', $dealer) }}">{{ $dealer->name }}</a><p class="mt-1 text-slate-500">{{ $dealer->code }}</p></td>
                        <td class="p-4"><p>{{ $dealer->province ?? 'Chưa có tỉnh/thành phố' }}</p><p class="mt-1 text-slate-500">{{ $dealer->phone ?? 'Chưa có điện thoại' }}</p></td>
                        <td class="p-4">{{ $dealer->sales_count }}</td>
                        <td class="whitespace-nowrap p-4"><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $dealer->is_active ? 'bg-green-50 text-green-800' : 'bg-slate-100 text-slate-700' }}">{{ $dealer->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="p-4"><div class="flex items-center gap-4 whitespace-nowrap">
                            <a href="{{ route('admin.dealers.edit', $dealer) }}" class="py-2 font-medium text-red-600 hover:underline">Sửa</a>
                            <form method="POST" action="{{ route('admin.dealers.status', $dealer) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $dealer->is_active ? '0' : '1' }}">
                                <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50">{{ $dealer->is_active ? 'Ngừng hoạt động' : 'Kích hoạt' }}</button>
                            </form>
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-slate-500">Không tìm thấy đại lý phù hợp.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $dealers->links() }}</div>
</x-layout>
