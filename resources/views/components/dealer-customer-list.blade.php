@props(['customers'])
<ul class="mt-4 divide-y divide-slate-100">
    @forelse ($customers as $customer)
        <li class="flex flex-wrap items-center justify-between gap-3 py-4"><div class="min-w-0"><a href="{{ route('dealer.customers.show', $customer) }}" class="break-words font-semibold text-red-700 hover:underline">{{ $customer->customer_name }}</a><p class="mt-2 break-words text-sm text-slate-500"><x-sales-link :sales="$customer->sales" /> · {{ $customer->phone ?: 'Chưa có số điện thoại' }}</p><p class="mt-1 text-xs text-slate-500">{{ $customer->submitted_at->format('d/m/Y H:i') }}</p></div><x-submission-status :status="$customer->status" /></li>
    @empty
        <li class="py-5 text-sm text-slate-500">Không có khách hàng phù hợp.</li>
    @endforelse
</ul>
