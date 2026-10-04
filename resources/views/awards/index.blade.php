<x-layout title="Lịch sử vinh danh">
    <p class="text-xs font-bold uppercase tracking-widest text-red-600">Toyota Veloz & Hilux</p>
    <h1 class="mt-3 text-3xl font-bold sm:text-4xl">Lịch sử vinh danh</h1>
    <p class="mt-3 text-sm leading-6 text-slate-600">Các kết quả tuần và tháng đã được công bố, ghi nhận thành tích của Sales và Dealer trong cộng đồng.</p>
    <nav aria-label="Loại kỳ vinh danh" class="mt-6 flex flex-wrap gap-3">
        @foreach (['' => 'Tất cả', 'weekly' => 'Tuần', 'monthly' => 'Tháng'] as $value => $label)
            <a href="{{ route('awards.index', $value === '' ? [] : ['period_type' => $value]) }}" @if ($periodType === $value) aria-current="page" @endif class="rounded-xl border px-4 py-3 text-sm font-semibold {{ $periodType === $value ? 'border-red-200 bg-red-50 text-red-700' : 'border-slate-200 bg-white text-slate-600' }}">{{ $label }}</a>
        @endforeach
    </nav>
    <ul class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($awards as $award)
            <li class="rounded-2xl border border-slate-200 bg-white p-6">
                <p class="text-xs font-bold uppercase tracking-widest text-red-600">Vinh danh {{ mb_strtolower($award->period_type->label()) }}</p>
                <h2 class="mt-3 break-words text-lg font-bold"><a href="{{ route('awards.show', $award) }}" class="hover:text-red-600">{{ $award->title }}</a></h2>
                <p class="mt-3 text-sm text-slate-500">{{ $award->period_start->format('d/m/Y') }} – {{ $award->period_end->subDay()->format('d/m/Y') }}</p>
                <p class="mt-2 text-xs text-slate-500">Công bố {{ $award->published_at->format('d/m/Y H:i') }}</p>
                <a href="{{ route('awards.show', $award) }}" class="mt-5 inline-block py-2 text-sm font-semibold text-red-600">Xem kết quả →</a>
            </li>
        @empty
            <li class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-600 sm:col-span-2 lg:col-span-3">Chưa có kết quả vinh danh được công bố.</li>
        @endforelse
    </ul>
    <div class="mt-6">{{ $awards->links() }}</div>
</x-layout>
