<x-layout title="Kết quả vinh danh">
    <a href="{{ route('awards.index') }}" class="text-sm text-slate-500 hover:text-red-600">← Lịch sử vinh danh</a>
    <h1 class="mt-4 break-words text-3xl font-bold">{{ $award->period_type->label() }} · {{ $award->period_start->format('d/m/Y') }}</h1>
    <p class="mt-3 text-sm text-slate-600">{{ $award->period_type->label() }} · {{ $award->period_start->format('d/m/Y') }} – {{ $award->period_end->subDay()->format('d/m/Y') }}</p>
    <p class="mt-2 text-xs text-slate-500">Công bố {{ $award->published_at->format('d/m/Y H:i') }} · Múi giờ {{ config('app.timezone') }}</p>
    <div class="mt-8">
        <x-award-results :dealers="$award->winners" />
    </div>
    <p class="mt-7 text-xs text-slate-500">Kết quả đã lưu tại thời điểm công bố. Thành tích được duyệt sau đó không thay đổi lịch sử này.</p>
</x-layout>
