<x-layout :title="$dealer->name">
    <a href="{{ route('admin.dealers.index') }}" class="text-sm text-slate-500 hover:text-red-600">← Danh sách đại lý</a>
    <div class="mb-7 mt-4 flex flex-wrap items-center justify-between gap-4"><h1 class="text-3xl font-bold break-words">{{ $dealer->name }}</h1><a href="{{ route('admin.dealers.edit', $dealer) }}" class="button-primary">Chỉnh sửa đại lý</a></div>
    <x-dealer-profile-media :dealer="$dealer" />
    <a href="{{ route('admin.dealers.account.edit', $dealer) }}" class="my-5 inline-flex rounded-lg border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-red-700 hover:bg-slate-50">Quản lý tài khoản Dealer</a>
    <div class="mt-6"><x-dealer-details :dealer="$dealer" :standing="$standing" /></div>
    <section class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-semibold">Sales thuộc đại lý ({{ $sales->total() }})</h2><a href="{{ route('admin.sales.index', ['dealer_id' => $dealer->id]) }}" class="py-2 text-sm font-semibold text-red-700 hover:underline">Quản lý Sales →</a></div>
        <ul class="mt-4 divide-y divide-slate-100">
            @forelse ($sales as $person)
                <li class="flex flex-wrap items-center justify-between gap-3 py-4"><div class="min-w-0"><a href="{{ route('admin.sales.show', $person) }}" class="font-semibold break-words hover:text-red-700 hover:underline">{{ $person->name }}</a><p class="mt-1 text-sm text-slate-500 break-all">{{ $person->email }}</p></div><x-user-status :status="$person->status" /></li>
            @empty
                <li class="py-5 text-sm text-slate-500">Chưa có Sales thuộc đại lý.</li>
            @endforelse
        </ul>
        <div class="mt-5">{{ $sales->links() }}</div>
    </section>
    <section class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-semibold">Khách hàng của đại lý ({{ number_format($customers->total()) }})</h2><a href="{{ route('admin.submissions.index', ['dealer_id' => $dealer->id, 'status' => '']) }}" class="py-2 text-sm font-semibold text-red-700 hover:underline">Quản lý khai báo →</a></div>
        <p class="mt-3 text-sm text-slate-500">{{ number_format($approvedCustomers) }} đã duyệt · Danh sách gồm mọi trạng thái. Khách hàng cũ được giữ tại đại lý khai báo ban đầu khi Sales chuyển đại lý.</p>
        <ul class="mt-4 divide-y divide-slate-100">
            @forelse ($customers as $customer)
                <li class="flex flex-wrap items-center justify-between gap-3 py-4">
                    <div class="min-w-0"><a href="{{ route('admin.submissions.show', $customer) }}" class="break-words font-semibold hover:text-red-700 hover:underline">{{ $customer->customer_name }}</a><p class="mt-1 break-words text-sm text-slate-500"><x-sales-link :sales="$customer->sales" /> · {{ $customer->phone ?: 'Chưa có số điện thoại' }}</p><p class="mt-1 text-xs text-slate-500">{{ $customer->submitted_at->format('d/m/Y H:i') }}</p></div>
                    <x-submission-status :status="$customer->status" />
                </li>
            @empty
                <li class="py-5 text-sm text-slate-500">Chưa có khách hàng được khai báo cho đại lý.</li>
            @endforelse
        </ul>
        <div class="mt-5">{{ $customers->links() }}</div>
    </section>
</x-layout>
