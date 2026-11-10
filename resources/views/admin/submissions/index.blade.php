<x-admin.layout title="Duyệt khách hàng">
    @vite(['resources/css/admin-submissions.css', 'resources/js/admin-submissions.js'])
    @php
        $number = fn ($value) => number_format($value, 0, ',', '.');
        $queueUrl = fn ($changes = []) => route('admin.submissions.index', array_replace($filters, $changes));
        $maskPhone = function ($phone) {
            $digits = preg_replace('/\D/', '', $phone ?? '');
            return strlen($digits) >= 9 && strlen($digits) <= 15 ? substr($digits, 0, 2).str_repeat('*', strlen($digits) - 6).substr($digits, -4) : '';
        };
        $maskPlate = fn ($plate) => filled($plate) ? substr(\App\Services\SubmissionIntake::plate($plate), 0, 3).'-xxx.xx' : '';
        $from = filled($filters['submitted_from'] ?? null) ? \Carbon\CarbonImmutable::parse($filters['submitted_from']) : null;
        $to = filled($filters['submitted_to'] ?? null) ? \Carbon\CarbonImmutable::parse($filters['submitted_to']) : null;
        $dateLabel = implode(' – ', array_filter([$from?->format($to && $to->year === $from->year ? 'd/m' : 'd/m/Y'), $to?->format('d/m/Y')]));
        $tabs = [];
        foreach (\App\Services\AdminSubmissionQueue::TABS as $key => $label) {
            $tabs[] = ['key' => $key, 'label' => $label, 'count' => $tabCounts[$key], 'href' => $queueUrl(['workflow' => $key, 'status' => in_array($key, ['approved', 'rejected']) ? $key : 'pending', 'page' => null, 'selected' => null])];
        }
    @endphp
    <div class="cq-page" data-cq-page data-page="{{ $submissions->currentPage() }}" data-last-page="{{ $submissions->lastPage() }}" data-selected="{{ $selected?->id }}" data-flash-toast="{{ session('cq_toast') }}">
        <header class="cq-head"><h1>Duyệt khách hàng</h1><p aria-hidden="true"></p></header>
        {{-- Flag-priority subtitle is blocked until geographic/burst contracts exist. --}}
        <div class="cq-toolbar">
            <x-admin.tabs :items="$tabs" :active="$workflow" />
            <form method="GET" action="{{ route('admin.submissions.index') }}" class="cq-filters" data-cq-filters>
                <input type="hidden" name="status" value="{{ $status }}">
                @if ($workflow)<input type="hidden" name="workflow" value="{{ $workflow }}">@endif
                @if (filled($filters['sales_id'] ?? null))<input type="hidden" name="sales_id" value="{{ $filters['sales_id'] }}">@endif
                <label class="cq-search"><img src="{{ asset('admin/icons/c02-search.png') }}" width="16" height="16" alt=""><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tên, SĐT, biển số, Facebook" aria-label="Tên, SĐT, biển số, Facebook" maxlength="255"></label>
                <select class="cq-dealer" name="dealer_id" aria-label="Tất cả đại lý"><option value="">Tất cả đại lý</option>@foreach ($dealers as $dealer)<option value="{{ $dealer->id }}" @selected((string) ($filters['dealer_id'] ?? '') === (string) $dealer->id)>{{ $dealer->name }}</option>@endforeach</select>
                <select class="cq-source" name="source" aria-label="Tất cả nguồn"><option value="">Tất cả nguồn</option><option value="dealer_qr" @selected(($filters['source'] ?? '') === 'dealer_qr')>QR đại lý</option><option disabled>Khai báo tay</option><option disabled>Link sales</option></select>
                <div class="cq-dates"><span>{{ $dateLabel }}</span><input type="date" name="submitted_from" aria-label="Từ ngày khai báo" value="{{ $filters['submitted_from'] ?? '' }}"><input type="date" name="submitted_to" aria-label="Đến ngày khai báo" value="{{ $filters['submitted_to'] ?? '' }}"></div>
                <x-admin.button variant="warning-soft" size="38" class="cq-flags" disabled><img src="{{ asset('admin/icons/c02-flag.png') }}" width="16" height="16" alt="">Chỉ ca bị gắn cờ</x-admin.button>
            </form>
        </div>
        <div class="cq-split">
            <div class="cq-list">
                <div class="cq-list-head"><span>KHÁCH HÀNG</span><span>ĐẠI LÝ / SALES</span><span>THỜI ĐIỂM</span></div>
                @foreach ($submissions as $submission)
                    <a href="{{ $queueUrl(['selected' => $submission->id]) }}" @class(['cq-row', 'cq-selected' => $selected?->is($submission), 'cq-row-sales' => $submission->source === 'sales']) @if ($selected?->is($submission)) aria-current="true" @endif>
                        <div class="cq-customer"><span class="cq-flag-slot" aria-hidden="true"></span><div><strong>{{ $submission->customer_name }}</strong><small>{{ $maskPhone($submission->phone) }}</small></div></div>
                        <div class="cq-attribution"><strong>{{ $submission->dealer?->name }}</strong><small>{{ $submission->source === 'dealer_qr' ? 'Khách tự đăng ký · QR đại lý' : ($submission->sales?->role === \App\Enums\UserRole::Sales ? $submission->sales->name : '') }}</small></div>
                        <time datetime="{{ $submission->submitted_at->toIso8601String() }}">{{ $submission->submitted_at->format('d/m H:i') }}</time>
                    </a>
                @endforeach
                <nav class="cq-pagination" aria-label="Phân trang"><span>{{ $submissions->firstItem() ?? 0 }}–{{ $submissions->lastItem() ?? 0 }} / {{ $number($submissions->total()) }}</span>
                    @foreach (['previous' => $submissions->previousPageUrl(), 'next' => $submissions->nextPageUrl()] as $direction => $url)
                        @if ($url)<a class="cq-circle" href="{{ $url }}" aria-label="{{ $direction === 'previous' ? 'Trang trước' : 'Trang sau' }}"><img src="{{ asset('admin/icons/c02-'.$direction.'.png') }}" width="14" height="14" alt=""></a>
                        @else<span class="cq-circle" aria-disabled="true"><img src="{{ asset('admin/icons/c02-'.$direction.'.png') }}" width="14" height="14" alt=""></span>@endif
                    @endforeach
                </nav>
            </div>
            @if ($selected) @include('admin.submissions.queue-detail') @endif
        </div>
        @if ($selected && $editingAvailable) @include('admin.submissions.edit-modal') @endif
        <x-admin.toast id="cq-toast"><span data-cq-toast-message></span></x-admin.toast>
    </div>
</x-admin.layout>
