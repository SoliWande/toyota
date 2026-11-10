<x-admin.layout title="Duyệt tài khoản">
    @vite(['resources/css/admin-account-approval.css', 'resources/js/admin-account-approval.js'])
    <div class="ac-page" data-account-approval>
        <header class="ac-head"><h1>Duyệt tài khoản</h1><p>Admin duyệt quản lý đại lý. Sales do quản lý đại lý của họ duyệt.</p></header>
        <x-admin.tabs :items="[
            ['key' => 'pending', 'label' => 'Quản lý đại lý chờ duyệt', 'count' => $pendingCount, 'alert' => true, 'href' => route('admin.dealer-registrations.index', ['tab' => 'pending'])],
            ['key' => 'overdue', 'label' => 'Sales chờ quá 3 ngày', 'count' => $overdueSales->count(), 'alert' => true, 'href' => route('admin.dealer-registrations.index', ['tab' => 'overdue'])],
            ['key' => 'processed', 'label' => 'Đã xử lý'],
        ]" :active="$tab" />
        @foreach ($accounts as $account)
            @php
                $digits = preg_replace('/\D/', '', $account->phone ?? '');
                $maskedPhone = strlen($digits) >= 9 && strlen($digits) <= 15 ? substr($digits, 0, 4).' xxx '.substr($digits, -3) : '';
                $canExplainApproval = $account->dealer?->is_active && !$account->dealer->manager;
            @endphp
            <section class="ac-split" data-ac-account="{{ $account->id }}">
                <div class="ac-card ac-applicant">
                    <header class="ac-person"><span class="ac-avatar">{{ mb_strtoupper(mb_substr(trim($account->name), 0, 1)) }}</span><div><h2>{{ $account->name }}</h2><x-admin.badge variant="brand">Muốn làm Quản lý đại lý</x-admin.badge></div></header>
                    <dl class="ac-info">
                        @foreach (['Đại lý' => $account->dealer?->name, 'Email' => $account->email, 'Điện thoại' => $maskedPhone, 'Đăng ký' => $account->created_at->format('d/m/Y, H:i')] as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach
                    </dl>
                    <div class="ac-decisions"><form method="POST" action="{{ route('admin.dealer-registrations.review', [$account, 'approve']) }}" data-ac-review>@csrf<x-admin.button type="submit" class="ac-approve"><img src="{{ asset('admin/icons/c06-approve.png') }}" width="16" height="16" alt="">Duyệt</x-admin.button></form><x-admin.button variant="danger-outline" class="ac-reject-toggle" data-ac-reject aria-expanded="true" :aria-controls="'ac-reject-'.$account->id">Từ chối</x-admin.button></div>
                    <form id="ac-reject-{{ $account->id }}" class="ac-rejection" method="POST" action="{{ route('admin.dealer-registrations.review', [$account, 'reject']) }}" data-ac-review data-ac-rejection>
                        @csrf
                        <label for="ac-reason-{{ $account->id }}">Lý do từ chối (người đăng ký sẽ thấy)</label>
                        <select id="ac-reason-{{ $account->id }}" name="rejection_reason"><option>Không phải nhân viên của đại lý</option></select>
                        <textarea name="rejection_note" aria-label="Ghi thêm (tuỳ chọn)" placeholder="Ghi thêm (tuỳ chọn)" maxlength="2000" rows="2"></textarea>
                        <div class="ac-rejection-actions"><x-admin.button variant="secondary" size="38" data-ac-cancel>Huỷ</x-admin.button><x-admin.button variant="danger" size="36" type="submit">Gửi từ chối</x-admin.button></div>
                    </form>
                    <p class="ac-error" role="alert" data-ac-error hidden></p>
                </div>
                <aside class="ac-card ac-checks">
                    <h3>HỆ THỐNG KIỂM TRA GIÚP</h3>
                    {{-- No evaluator exists for Dealer email domain or phone ownership. Reserve their design slots, render no result. --}}
                    <div class="ac-unverified-slots" aria-hidden="true"></div>
                    @if ($canExplainApproval)<div class="ac-check-info"><img src="{{ asset('admin/icons/c06-info.png') }}" width="20" height="20" alt=""><p>{{ $account->dealer->name }} chưa có quản lý. Duyệt xong, người này sẽ duyệt sales của đại lý.</p></div>@endif
                    <div class="ac-check-note"><img src="{{ asset('admin/icons/c06-phone.png') }}" width="20" height="20" alt=""><p>Nếu còn nghi ngờ, gọi tổng đài của đại lý để xác nhận trước khi duyệt.</p></div>
                </aside>
            </section>
        @endforeach
        <section class="ac-overdue ac-card">
            <header><h2>Sales chờ quá 3 ngày</h2><p aria-hidden="true"></p></header>
            {{-- Reminder delivery and Admin override are BLOCKED. Do not promise either in the subtitle. --}}
            @foreach ($overdueSales as $sales)
                <div class="ac-sales-row"><div class="ac-sales-person"><strong>{{ $sales->name }}</strong><small>Sales · {{ $sales->dealer?->name }}</small></div><div class="ac-sales-manager"><strong>{{ $sales->dealer?->manager?->name }}</strong><small>Người duyệt</small></div><span class="ac-wait"><img src="{{ asset('admin/icons/c06-clock.png') }}" width="13" height="13" alt="">Chờ {{ number_format($sales->waiting_days, 0, ',', '.') }} ngày</span><x-admin.button variant="secondary" size="36" disabled class="ac-remind"><img src="{{ asset('admin/icons/c06-bell.png') }}" width="16" height="16" alt="">Nhắc quản lý</x-admin.button><x-admin.button size="36" disabled class="ac-override">Duyệt thay</x-admin.button></div>
            @endforeach
        </section>
        <x-admin.toast id="ac-toast"><span data-ac-toast-message></span></x-admin.toast>
    </div>
</x-admin.layout>
