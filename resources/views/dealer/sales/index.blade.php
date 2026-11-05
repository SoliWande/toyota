<x-competition.account-layout :title="'Nhân viên · '.$dealer->name" :staff="true">
    <header class="wc-staff-title"><div><p>QUẢN LÝ ĐẠI LÝ</p><h1>Nhân viên</h1><small>{{ $dealer->name }}</small></div>
        <nav class="wc-staff-tabs" aria-label="Trạng thái nhân viên">
            @foreach (['active' => 'Đang làm', 'pending' => 'Chờ duyệt', 'blocked' => 'Đã khóa', 'rejected' => 'Từ chối'] as $value => $label)<a href="{{ route('dealer.sales.index', array_filter(['status' => $value, 'q' => $filters['q'] ?? null])) }}" @if (($filters['status'] ?? '') === $value) aria-current="page" @endif>{{ $label }} <span>{{ $statusCounts[$value] ?? 0 }}</span></a>@endforeach
        </nav>
    </header>
    @if (session('success'))<p class="wc-auth-success" role="status">{{ session('success') }}</p>@endif
    @if ($errors->any())<div class="wc-auth-error" role="alert">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    @if (!in_array($filters['status'] ?? '', ['blocked', 'rejected'], true))<x-competition.pending-staff :people="($filters['status'] ?? '') === 'pending' ? $sales : $pendingSales" :count="($filters['status'] ?? '') === 'pending' ? $sales->total() : (int) ($statusCounts['pending'] ?? 0)" />@endif
    <section class="wc-staff-card wc-staff-list">
        <header class="wc-staff-section-title"><h2>Danh sách nhân viên <span>{{ $sales->total() }}</span></h2>
            <form method="GET" action="{{ route('dealer.sales.index') }}" class="wc-staff-search"><label class="wc-visually-hidden" for="q">Tên, email hoặc điện thoại</label><input id="q" name="q" placeholder="Tìm nhân viên…" value="{{ $filters['q'] ?? '' }}" maxlength="255"><input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}"><button class="wc-staff-button">Tìm</button></form>
        </header>
        @if (($filters['status'] ?? '') !== 'pending')
        <div class="wc-staff-table-head"><span>NHÂN VIÊN</span><span>ĐIỂM THÁNG</span><span>KHAI BÁO GẦN NHẤT</span><span>TRẠNG THÁI</span></div>
        @forelse ($sales as $person)
            <article class="wc-staff-table-row"><div class="wc-staff-person"><span class="wc-staff-initial">{{ mb_substr($person->name, 0, 1) }}</span><div><a href="{{ route('dealer.sales.show', $person) }}"><strong>{{ $person->name }}</strong></a><p>{{ $person->email }}</p>@if ($person->phone)<small>{{ $person->phone }}</small>@endif</div></div><p class="wc-staff-score"><b>{{ number_format($person->monthly_score) }}</b><small>điểm tháng</small></p><p>{{ $person->submissions_max_submitted_at ? \Carbon\Carbon::parse($person->submissions_max_submitted_at)->format('d/m/Y H:i') : 'Chưa khai báo' }}</p><div><span class="wc-staff-status wc-staff-status-{{ $person->status->value }}">{{ $person->status->label() }}</span>
            @if (in_array($person->status, [\App\Enums\UserStatus::Active, \App\Enums\UserStatus::Blocked], true))
                <details class="wc-staff-manage"><summary>Quản lý</summary><form method="POST" action="{{ route('dealer.staff.review', [$person, $person->status === \App\Enums\UserStatus::Active ? 'block' : 'reactivate']) }}" data-account-form>@csrf<p>{{ $person->status === \App\Enums\UserStatus::Active ? 'Khóa quyền truy cập tài khoản này?' : 'Mở khóa tài khoản này?' }}</p><button class="wc-staff-button" data-account-submit>Xác nhận {{ $person->status === \App\Enums\UserStatus::Active ? 'khóa' : 'mở khóa' }}</button></form></details>
            @endif</div></article>
        @empty<p class="wc-staff-empty">Không có Sales phù hợp.</p>@endforelse
        @endif
        <div class="wc-staff-pagination"><x-competition.staff-pagination :rows="$sales" /></div>
    </section>
</x-competition.account-layout>
