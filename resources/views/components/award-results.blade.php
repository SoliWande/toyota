@props(['sales', 'dealers'])
<div class="space-y-8">
    @foreach (['Top 3 Sales' => $sales, 'Top 3 Đại Lý' => $dealers] as $title => $winners)
        <section>
            <h2 class="text-xl font-bold">{{ $title }}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                @forelse ($winners as $winner)
                    <div class="rounded-2xl border p-5 {{ (int) $winner['rank'] === 1 ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-white' }}">
                        <p class="text-lg font-bold text-red-600">#{{ $winner['rank'] }}</p>
                        <h3 class="mt-3 break-words text-lg font-semibold">{{ $winner['winner_name_snapshot'] }}</h3>
                        <p class="mt-2 break-words text-sm text-slate-500">{{ $winner['dealer_name_snapshot'] }} · {{ $winner['dealer_code_snapshot'] }}</p>
                        <p class="mt-5 text-3xl font-bold">{{ number_format($winner['score'], 0, ',', '.') }} <span class="text-sm font-normal text-slate-500">điểm</span></p>
                    </div>
                @empty
                    <p class="rounded-2xl border border-dashed border-slate-300 p-6 text-sm text-slate-500 sm:col-span-3">Chưa có thành tích hợp lệ trong kỳ này.</p>
                @endforelse
            </div>
        </section>
    @endforeach
</div>
