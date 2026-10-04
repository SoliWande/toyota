<x-layout title="Bảng xếp hạng">
    <p class="text-xs font-bold uppercase tracking-widest text-red-600">Toyota Veloz & Hilux</p>
    <h1 class="mt-3 text-3xl font-bold sm:text-4xl">Bảng xếp hạng cộng đồng</h1>
    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Vinh danh Sales và Dealer mời thành viên tham gia cộng đồng. Mỗi khai báo đã duyệt được tính một điểm, theo ngày khai báo.</p>
    <nav aria-label="Chọn kỳ xếp hạng" class="mt-7 grid grid-cols-3 gap-2 rounded-2xl bg-slate-200 p-2 sm:max-w-xl">
        @foreach (\App\Enums\LeaderboardPeriod::cases() as $option)
            <a href="{{ route('leaderboard', ['period' => $option->value]) }}" @if ($period === $option) aria-current="page" @endif
                class="rounded-xl px-3 py-3 text-center text-sm font-semibold {{ $period === $option ? 'bg-white text-red-600 shadow-sm' : 'text-slate-600 hover:bg-white/60' }}">{{ $option->label() }}</a>
        @endforeach
    </nav>
    <p class="mt-3 text-xs text-slate-500">
        @if ($bounds[0])
            {{ $bounds[0]->format('d/m/Y') }} – {{ $bounds[1]->subDay()->format('d/m/Y') }} ·
        @endif
        Múi giờ {{ config('app.timezone') }}
    </p>
    <p class="mt-2 text-xs text-slate-500">Bằng điểm: ưu tiên người đạt điểm sớm hơn theo thời điểm khai báo.</p>
    <div class="mt-9 space-y-12">
        <x-leaderboard-section title="Top Sales" :leaders="$topSales" :rows="$sales" type="sales" />
        <x-leaderboard-section title="Top Dealer" :leaders="$topDealers" :rows="$dealers" type="dealer" />
    </div>
</x-layout>
