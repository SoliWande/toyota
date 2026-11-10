<x-admin.layout title="Đại lý">
    @vite(['resources/css/admin-dealers.css', 'resources/js/admin-dealers.js'])
    @php
        $number = fn ($value) => number_format($value, 0, ',', '.');
        $listUrl = fn ($changes = []) => route('admin.dealers.index', array_replace($filters, $changes));
        $profileUrl = $selected ? app(\App\Services\DealerProfile::class)->url($selected) : null;
        $items = [];
        foreach (['' => ['Tất cả', 'all'], 'no_customers' => ['Chưa có khách tháng này', 'no_customers'], 'no_manager' => ['Chưa có quản lý', 'no_manager'], 'inactive' => ['Ngừng tham gia', 'inactive']] as $key => [$label, $count]) {
            $items[] = ['key' => $key, 'label' => $label, 'count' => $counts[$count], 'alert' => in_array($key, ['no_customers', 'no_manager']), 'href' => $listUrl(['status' => $key, 'page' => null, 'selected' => null])];
        }
    @endphp
    <div class="dc-page" data-dealer-page>
        <header class="dc-head"><div><h1>Đại lý</h1><p>{{ $number($counts['all']) }} đại lý · {{ $number($counts['active']) }} đang tham gia</p></div><x-admin.button class="dc-add" data-dc-open="dc-create"><img src="{{ asset('admin/icons/c04-plus.png') }}" width="16" height="16" alt="">Thêm đại lý</x-admin.button></header>
        <x-admin.tabs :items="$items" :active="$filters['status'] ?? ''" />
        <form class="dc-filters" method="GET" action="{{ route('admin.dealers.index') }}" data-dc-filters>
            @if (filled($filters['status'] ?? null))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
            <label class="dc-search"><img src="{{ asset('admin/icons/c04-search.png') }}" width="16" height="16" alt=""><input name="q" aria-label="Tên hoặc mã đại lý" placeholder="Tên hoặc mã đại lý" value="{{ $filters['q'] ?? '' }}" maxlength="255"></label>
            <select name="province" aria-label="Tất cả tỉnh/thành"><option value="">Tất cả tỉnh/thành</option>@foreach ($provinces as $province)<option @selected(($filters['province'] ?? '') === $province)>{{ $province }}</option>@endforeach</select>
            <select name="sort" aria-label="Sắp xếp"><option value="month" @selected(($filters['sort'] ?? 'month') === 'month')>Sắp xếp: tháng này</option><option value="total" @selected(($filters['sort'] ?? '') === 'total')>Sắp xếp: tổng</option><option value="name" @selected(($filters['sort'] ?? '') === 'name')>Sắp xếp: tên</option></select>
        </form>
        <div class="dc-split">
            <div class="dc-list">
                <div class="dc-row dc-table-head"><span>ĐẠI LÝ</span><span>TỈNH</span><span>QUẢN LÝ</span><span>THÁNG NÀY</span><span>TỔNG</span></div>
                @foreach ($dealers as $dealer)
                    <a @class(['dc-row', 'dc-selected' => $selected?->is($dealer)]) href="{{ $listUrl(['selected' => $dealer->id]) }}" @if ($selected?->is($dealer)) aria-current="true" @endif>
                        <span class="dc-name"><strong>{{ $dealer->name }}</strong><small>{{ $dealer->code }}</small></span><span class="dc-province">{{ $dealer->province }}</span><span @class(['dc-manager', 'dc-missing' => !$dealer->manager])>{{ $dealer->manager?->name ?? 'Chưa có' }}</span><b @class(['dc-zero' => !$dealer->month_count])>{{ $number($dealer->month_count) }}</b><b @class(['dc-zero' => !$dealer->approved_count])>{{ $number($dealer->approved_count) }}</b>
                    </a>
                @endforeach
                <nav class="dc-pagination" aria-label="Phân trang"><span>{{ $dealers->firstItem() ?? 0 }}–{{ $dealers->lastItem() ?? 0 }} / {{ $number($dealers->total()) }}</span>@if ($dealers->previousPageUrl())<a href="{{ $dealers->previousPageUrl() }}">‹</a>@endif @if ($dealers->nextPageUrl())<a href="{{ $dealers->nextPageUrl() }}">Trang sau ›</a>@else<span aria-disabled="true">Trang sau ›</span>@endif</nav>
            </div>
            @if ($selected)
                <aside class="dc-panel">
                    <header><div><h2>{{ $selected->name }}</h2><x-admin.badge :variant="$selected->is_active ? 'approved' : 'neutral'">{{ $selected->is_active ? 'Đang tham gia' : 'Ngừng tham gia' }}</x-admin.badge></div><p>{{ $selected->code }} · {{ $selected->province }}</p></header>
                    <div class="dc-stats"><div><strong>{{ $number($selected->month_count) }}</strong><span>Đã xác minh tháng này</span></div><a href="{{ route('leaderboard', ['period' => 'month']) }}" target="_blank" rel="noopener"><strong>{{ $standing ? '#'.$standing->rank : '—' }}</strong><span>Hạng tháng này</span></a><div><strong>{{ $number($selected->active_sales_count) }}</strong><span>Sales đang làm</span></div></div>
                    <hr>
                    <div class="dc-section-head"><h3>THÔNG TIN ĐẠI LÝ</h3><button type="button" data-dc-open="dc-edit"><img src="{{ asset('admin/icons/c04-edit.png') }}" width="13" height="13" alt="">Sửa</button></div>
                    <dl class="dc-info">
                        @foreach (['address' => 'Địa chỉ', 'phone' => 'Điện thoại', 'opening_hours' => 'Giờ mở cửa'] as $field => $label)<div><dt>{{ $label }}</dt><dd>{{ $selected->$field }}</dd></div>@endforeach
                        <div><dt>Facebook, Zalo</dt><dd>@foreach (['facebook_url', 'zalo_url'] as $field)@if ($selected->$field && preg_match('~^https?://~i', $selected->$field))<a href="{{ $selected->$field }}" target="_blank" rel="noopener noreferrer">{{ $selected->$field }}</a>@if ($field === 'facebook_url' && $selected->zalo_url) · @endif @endif @endforeach</dd></div>
                        <div><dt>Quản lý</dt><dd>{{ $selected->manager?->name }}@if ($selected->manager) · {{ \Illuminate\Support\Str::before($selected->manager->email, '@') }}@…@endif</dd></div>
                    </dl>
                    <hr>
                    <h3>MÃ QR VÀ STANDEE</h3>
                    <button type="button" class="dc-copy" data-dc-copy="{{ $profileUrl }}"><img src="{{ asset('admin/icons/c04-link.png') }}" width="16" height="16" alt=""><span>…/{{ $selected->slug }}/</span><img src="{{ asset('admin/icons/c04-copy.png') }}" width="16" height="16" alt=""></button>
                    <div class="dc-downloads">@foreach (['png' => 'QR (PNG)', 'pdf' => 'Standee (PDF)'] as $format => $label)<x-admin.button variant="secondary" size="38" :href="route('admin.dealers.download', [$selected, $format])" data-dc-download :data-dc-toast="($format === 'png' ? 'Đang tải QR ' : 'Đang tải standee ').$selected->name.($format === 'png' ? ' (PNG)' : ' (PDF)')"><img src="{{ asset('admin/icons/c04-download.png') }}" width="16" height="16" alt="">{{ $label }}</x-admin.button>@endforeach</div>
                    @if ($selected->is_active)<div class="dc-deactivate"><div><strong>Ngừng tham gia chương trình</strong><p>Điểm cũ vẫn giữ.</p></div><x-admin.button variant="danger-outline" size="38" data-dc-open="dc-stop">Ngừng tham gia</x-admin.button></div>@endif
                </aside>
            @endif
        </div>
        @include('admin.dealers.modals')
        <x-admin.toast id="dc-toast"><span data-dc-toast-message></span></x-admin.toast>
        <p class="dc-error" data-dc-error role="alert" hidden></p>
    </div>
</x-admin.layout>
