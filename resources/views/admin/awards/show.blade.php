<x-layout title="Kết quả vinh danh">
    <a href="{{ route('admin.awards.index') }}" class="text-sm text-slate-500 hover:text-red-600">← Quản lý vinh danh</a>
    <h1 class="mt-3 break-words text-3xl font-bold">{{ $award->title }}</h1>
    <p class="mt-3 text-sm text-slate-600">{{ $award->period_type->label() }} · {{ $award->period_start->format('d/m/Y') }} – {{ $award->period_end->subDay()->format('d/m/Y') }} · Múi giờ {{ config('app.timezone') }}</p>
    <x-admin-feedback />
    @if ($preview !== null)
        <div class="my-6 rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <p class="font-semibold text-amber-900">Bản nháp — Preview</p>
            <p class="mt-2 text-sm leading-6 text-amber-800">Kết quả từ khai báo đã duyệt theo thời điểm khai báo. Preview có thể thay đổi khi có thêm khai báo được duyệt; chưa hiển thị public.</p>
        </div>
        <x-award-results :dealers="$preview['dealers']" />
        <form method="POST" action="{{ route('admin.awards.publish', $award) }}" class="my-7">
            @csrf
            <input type="hidden" name="preview_hash" value="{{ $previewHash }}">
            <p class="mb-3 text-sm text-slate-600">Chỉ công bố khi kỳ đã kết thúc và tất cả khai báo trong kỳ đã được xử lý. Kết quả sau công bố không thể sửa.</p>
            <button type="submit" class="button-primary">Công bố Top 3 đại lý</button>
        </form>
    @else
        <p class="my-6 rounded-xl bg-green-50 p-4 text-sm text-green-800">@if ($award->published_at) Đã công bố lúc {{ $award->published_at->format('d/m/Y H:i') }} bởi {{ $award->publisher->name }}. Kết quả lịch sử đã được lưu cố định. @else Bản nháp lịch sử được giữ để audit, không thể công bố theo quy tắc mới. @endif</p>
        <x-award-results :sales="$award->winners->filter(fn ($winner) => $winner->winner_type === \App\Enums\AwardWinnerType::Sales)" :dealers="$award->winners->filter(fn ($winner) => $winner->winner_type === \App\Enums\AwardWinnerType::Dealer)" />
        @if ($award->published_at)<a href="{{ route('awards.show', $award) }}" class="mt-7 inline-block text-sm font-semibold text-red-600 hover:underline">Xem trang công bố public</a>@endif
    @endif
</x-layout>
