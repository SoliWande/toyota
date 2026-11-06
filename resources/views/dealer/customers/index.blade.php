<x-competition.account-layout title="Khách của tôi" :staff="true" staff-page="manager-customers">
    <header class="wc-staff-title"><div><h1>Khách của tôi</h1><small>{{ $dealer->name }}</small></div></header>
    {{-- No dedicated Manager customer-list/empty-state frame exists on the Website Thi đua pages.
         Reuse the existing Manager shell and rows; do not invent Sales tabs, table columns or empty copy. --}}
    @if ($customers->isNotEmpty())
        <section class="wc-staff-card" aria-label="Khách của tôi">
            <x-dealer-customer-list :customers="$customers" />
            @if ($customers->hasPages())<x-competition.leaderboard-pagination :rows="$customers" label="Phân trang khách hàng" />@endif
        </section>
    @endif
</x-competition.account-layout>
