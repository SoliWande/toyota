<x-admin.layout title="Sales">
    @vite(['resources/css/admin-sales.css', 'resources/js/admin-sales.js'])
    @php
        $tabUrl = fn ($status) => route('admin.sales.index', array_merge(\Illuminate\Support\Arr::except($filters, ['page', 'lock', 'status']), ['status' => $status]));
        $tabs = [
            ['key' => 'active', 'label' => 'Đang hoạt động', 'count' => $counts['active'], 'href' => $tabUrl('active')],
            ['key' => 'high_rejection', 'label' => 'Tỉ lệ bị từ chối cao'],
            ['key' => 'blocked', 'label' => 'Đã khoá', 'count' => $counts['blocked'], 'href' => $tabUrl('blocked')],
        ];
    @endphp
    <div class="sc-page">
        <header class="sc-head">
            <div><h1>Sales</h1><p>{{ number_format($counts['active'], 0, ',', '.') }} tài khoản đang hoạt động</p></div>
            <button type="button" class="sc-export" disabled><img src="{{ asset('admin/icons/c05-download.png') }}" width="16" height="16" alt="">Xuất danh sách</button>
        </header>
        <x-admin.tabs :items="$tabs" :active="$activeStatus" aria-label="Trạng thái Sales" />
        <form method="GET" action="{{ route('admin.sales.index') }}" class="sc-filters" data-sales-filters>
            <input type="hidden" name="status" value="{{ $activeStatus }}">
            <label class="sc-search"><img src="{{ asset('admin/icons/c05-search.png') }}" width="16" height="16" alt=""><input name="q" value="{{ $filters['q'] ?? '' }}" maxlength="255" placeholder="Tên, email, số điện thoại" aria-label="Tên, email, số điện thoại"></label>
            <select name="dealer_id" aria-label="Đại lý">
                <option value="">Tất cả đại lý</option>
                @foreach ($dealers as $dealer)<option value="{{ $dealer->id }}" @selected((string) ($filters['dealer_id'] ?? '') === (string) $dealer->id)>{{ $dealer->name }}</option>@endforeach
            </select>
            <select name="role" aria-label="Vai trò"><option value="">Tất cả vai trò</option><option value="sales" @selected(($filters['role'] ?? '') === 'sales')>Sales</option></select>
        </form>
        @if ($errors->any())<div role="alert" class="sc-error">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <div class="sc-table" role="table" aria-label="Danh sách sales">
            <div class="sc-row sc-table-head" role="row"><span role="columnheader">NGƯỜI</span><span role="columnheader">VAI TRÒ</span><span role="columnheader">ĐẠI LÝ</span><span role="columnheader">THÁNG NÀY</span><span role="columnheader">BỊ TỪ CHỐI</span><span role="columnheader">HOẠT ĐỘNG</span><span role="columnheader" aria-label="Thao tác"></span></div>
            @foreach ($sales as $account)
                @php $confirming = (string) ($filters['lock'] ?? '') === (string) $account->id && $account->status === \App\Enums\UserStatus::Active; @endphp
                <div class="sc-row" role="row" data-sales-row="{{ $account->id }}">
                    <div role="cell" class="sc-person"><span class="sc-avatar">{{ mb_substr(\Illuminate\Support\Str::afterLast(trim($account->name), ' '), 0, 1) }}</span><a href="{{ route('admin.sales.show', $account) }}"><strong>{{ $account->name }}</strong><small>{{ \Illuminate\Support\Str::before($account->email, '@') }}@…</small></a></div>
                    <div role="cell"><x-admin.badge>Sales</x-admin.badge></div>
                    <div role="cell" class="sc-dealer">{{ $account->dealer?->name ?? '—' }}</div>
                    <div role="cell" @class(['sc-score', 'sc-zero' => (int) $account->month_score === 0])>{{ number_format($account->month_score, 0, ',', '.') }}</div>
                    <div role="cell" class="sc-rejection">—</div>
                    <div role="cell" @class(['sc-activity', 'sc-never' => ! $account->last_login_at])>{{ $account->last_login_at ? ($account->last_login_at->isYesterday() ? 'Hôm qua' : $account->last_login_at->locale('vi')->diffForHumans()) : 'Chưa đăng nhập' }}</div>
                    <div role="cell">
                        @if ($account->status === \App\Enums\UserStatus::Active)
                            <a @class(['sc-ellipsis', 'sc-confirming' => $confirming]) href="{{ route('admin.sales.index', array_merge($filters, ['lock' => $account->id])) }}" aria-label="Khoá {{ $account->name }}" aria-expanded="{{ $confirming ? 'true' : 'false' }}" @if ($confirming) aria-controls="sales-lock-{{ $account->id }}" @endif data-sales-open-confirm><img src="{{ asset('admin/icons/c05-ellipsis.png') }}" width="18" height="18" alt=""></a>
                        @else
                            <span class="sc-ellipsis" aria-disabled="true"><img src="{{ asset('admin/icons/c05-ellipsis.png') }}" width="18" height="18" alt=""></span>
                        @endif
                    </div>
                </div>
                @if ($confirming)
                    <form id="sales-lock-{{ $account->id }}" method="POST" action="{{ route('admin.sales.review', [$account, 'block']) }}" class="sc-confirm" data-sales-lock>
                        @csrf
                        <span class="sc-lock-icon"><img src="{{ asset('admin/icons/c05-lock.png') }}" width="18" height="18" alt=""></span>
                        <div><strong>Khoá {{ $account->name }}?</strong><p>Link mời hết hiệu lực ngay. {{ number_format($account->invited_count, 0, ',', '.') }} khách đã mời vẫn tính cho {{ $account->dealer?->name }}. Người này cũng không đăng nhập được nữa.</p></div>
                        <a class="ad-button ad-button-secondary ad-button-38" href="{{ route('admin.sales.index', \Illuminate\Support\Arr::except($filters, ['lock'])) }}">Huỷ</a>
                        <button type="submit" class="ad-button ad-button-danger ad-button-36">Khoá tài khoản</button>
                    </form>
                @endif
            @endforeach
            <nav class="sc-pagination" aria-label="Phân trang Sales"><span>{{ $sales->firstItem() ?? 0 }}–{{ $sales->lastItem() ?? 0 }} / {{ $sales->total() }}</span>@if ($sales->previousPageUrl())<a href="{{ $sales->previousPageUrl() }}">‹</a>@endif @if ($sales->nextPageUrl())<a href="{{ $sales->nextPageUrl() }}">Trang sau ›</a>@else<span aria-disabled="true">Trang sau ›</span>@endif</nav>
        </div>
    </div>
    @if (! $errors->any() && session('sales_blocked') && session('success'))
        <x-admin.toast id="sales-lock-success" class="sc-toast" data-sales-success>{{ session('success') }}</x-admin.toast>
    @endif
</x-admin.layout>
