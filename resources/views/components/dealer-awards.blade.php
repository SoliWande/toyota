@props(['awards'])
<ul class="mt-4 space-y-4">
    @forelse ($awards as $winner)
        <li class="rounded-xl border border-slate-200 p-5"><p class="font-semibold">{{ $winner->award->period_type->label() }} · {{ $winner->award->period_start->format('d/m/Y') }} – {{ $winner->award->period_end->subDay()->format('d/m/Y') }}</p><p class="mt-2 break-words text-sm text-slate-500"><x-dealer-link :dealer-id="$winner->dealer_id" :name="$winner->dealer_name_snapshot" /> · {{ $winner->dealer_code_snapshot }}</p><p class="mt-3 font-bold text-red-700">Hạng #{{ $winner->rank }} · {{ number_format($winner->score) }} điểm</p></li>
    @empty
        <li class="py-5 text-sm text-slate-500">Chưa có thành tích đại lý được công bố.</li>
    @endforelse
</ul>
