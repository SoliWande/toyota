<x-public.layout title="Đại lý đang hoạt động">
    <section class="public-container public-section">
        <a href="{{ route('home') }}" class="text-sm text-stone-600 hover:text-red-700">← Trang chủ</a>
        <h1 class="public-heading mt-5">Đại lý đang hoạt động</h1>
        <p class="mt-4 text-sm text-stone-600">{{ number_format($dealers->total()) }} đại lý · Điểm và thứ hạng toàn thời gian.</p>
        <form method="GET" action="{{ route('dealers.index') }}" class="my-7 flex flex-wrap items-end gap-3">
            <div class="min-w-0 grow"><label for="q" class="text-sm font-medium">Tên đại lý hoặc tỉnh / thành phố</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="255" class="form-input"></div>
            <button type="submit" class="public-button">Tìm kiếm</button>
        </form>
        <div class="grid gap-4 md:grid-cols-2">
            @forelse ($dealers as $dealer)
                <article class="min-w-0 rounded-2xl border border-stone-200 bg-white p-5">
                    <h2 class="break-words text-lg font-semibold"><a href="{{ route('dealers.show', $dealer) }}" class="hover:text-red-700 hover:underline">{{ $dealer->name }}</a></h2>
                    <p class="mt-2 text-sm text-stone-600 break-words">{{ $dealer->province }} · {{ $dealer->address ?: 'Chưa cập nhật địa chỉ' }}</p>
                    <p class="mt-2 text-sm text-stone-600">{{ $dealer->phone ?: 'Chưa cập nhật điện thoại' }}</p>
                    <p class="mt-4 font-semibold text-red-700">{{ number_format($dealer->score) }} điểm <span class="ml-3 font-normal text-stone-500">{{ $dealer->rank ? 'Hạng #'.$dealer->rank : 'Chưa xếp hạng' }}</span></p>
                </article>
            @empty
                <p class="py-8 text-stone-500">Không tìm thấy đại lý phù hợp.</p>
            @endforelse
        </div>
        <div class="mt-7">{{ $dealers->links() }}</div>
    </section>
</x-public.layout>
