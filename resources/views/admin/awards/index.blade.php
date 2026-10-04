<x-layout title="Quản lý vinh danh">
    <a href="{{ route('admin.dashboard') }}" class="text-sm text-slate-500 hover:text-red-600">← Quản trị</a>
    <h1 class="mt-3 text-3xl font-bold">Awards / Vinh danh</h1>
    <x-admin-feedback />
    <form method="POST" action="{{ route('admin.awards.store') }}" class="mt-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
        @csrf
        <div>
            <label for="period_type" class="text-sm font-medium">Loại kỳ</label>
            <select id="period_type" name="period_type" class="form-input" required>
                @foreach (\App\Enums\AwardPeriod::cases() as $period)
                    <option value="{{ $period->value }}" @selected(old('period_type') === $period->value)>{{ $period->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="period_date" class="text-sm font-medium">Một ngày thuộc kỳ muốn vinh danh</label>
            <input id="period_date" name="period_date" type="date" required value="{{ old('period_date', now(config('app.timezone'))->toDateString()) }}" class="form-input">
        </div>
        <div class="sm:col-span-2">
            <label for="title" class="text-sm font-medium">Tên kỳ vinh danh (tùy chọn)</label>
            <input id="title" name="title" maxlength="255" value="{{ old('title') }}" class="form-input">
        </div>
        <div class="sm:col-span-2">
            <p class="mb-4 text-sm text-slate-500">Tuần bắt đầu thứ Hai; tháng bắt đầu ngày 1. Múi giờ {{ config('app.timezone') }}. Kỳ đã tồn tại sẽ được mở lại.</p>
            <button type="submit" class="button-primary">Tạo bản nháp / Xem kỳ</button>
        </div>
    </form>
    <h2 class="mt-9 text-xl font-bold">Các kỳ vinh danh</h2>
    <ul class="mt-4 space-y-3">
        @forelse ($awards as $award)
            <li class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <a href="{{ route('admin.awards.show', $award) }}" class="break-words font-semibold hover:text-red-600">{{ $award->title }}</a>
                    <p class="mt-2 text-sm text-slate-500">{{ $award->period_type->label() }} · {{ $award->period_start->format('d/m/Y') }} – {{ $award->period_end->subDay()->format('d/m/Y') }}</p>
                </div>
                <span class="self-start rounded-full px-3 py-1 text-xs font-semibold {{ $award->published_at ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-800' }}">{{ $award->published_at ? 'Đã công bố' : 'Bản nháp' }}</span>
            </li>
        @empty
            <li class="p-6 text-center text-sm text-slate-500">Chưa có kỳ vinh danh.</li>
        @endforelse
    </ul>
    <div class="mt-5">{{ $awards->links() }}</div>
</x-layout>
