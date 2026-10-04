@props(['title', 'leaders', 'rows', 'type'])
<section aria-labelledby="{{ $type }}-heading">
    <h2 id="{{ $type }}-heading" class="text-2xl font-bold">{{ $title }}</h2>
    @if ($leaders->isEmpty())
        <div class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">Chưa có thành tích đã duyệt trong kỳ này.</div>
    @else
        <div class="mt-5 grid gap-4 sm:grid-cols-3">
            @foreach ($leaders as $leader)
                <div class="rounded-2xl border p-5 {{ (int) $leader->rank === 1 ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-white' }}">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white text-lg font-bold text-red-600 ring-1 ring-red-100">{{ $leader->rank }}</span>
                    <h3 class="mt-4 break-words text-lg font-bold">{{ $leader->name }}</h3>
                    <p class="mt-1 break-words text-sm text-slate-500">{{ $type === 'sales' ? $leader->dealer_name : $leader->code }}</p>
                    <p class="mt-5 text-3xl font-bold text-red-600">{{ number_format($leader->score, 0, ',', '.') }} <span class="text-sm font-normal text-slate-600">điểm</span></p>
                </div>
            @endforeach
        </div>
        <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">{{ $title }} — bảng thứ hạng đầy đủ</caption>
                <thead class="bg-slate-100 text-slate-600"><tr><th scope="col" class="p-4">Hạng</th><th scope="col" class="p-4">{{ $type === 'sales' ? 'Sales' : 'Dealer' }}</th><th scope="col" class="p-4">{{ $type === 'sales' ? 'Dealer' : 'Mã Dealer' }}</th><th scope="col" class="p-4 text-right">Điểm</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $row)
                        <tr class="{{ (int) $row->rank <= 3 ? 'bg-red-50/40' : '' }}">
                            <td class="p-4 font-bold">{{ $row->rank }}</td>
                            <td class="p-4 font-semibold">{{ $row->name }}</td>
                            <td class="p-4 text-slate-500">{{ $type === 'sales' ? $row->dealer_name : $row->code }}</td>
                            <td class="p-4 text-right font-bold">{{ number_format($row->score, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    @endif
</section>
