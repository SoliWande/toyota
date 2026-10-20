<x-layout :title="'Dashboard · '.$dealer->name">
    <x-dealer-nav />
    <p class="text-sm font-semibold text-red-700">Không gian đại lý</p>
    <h1 class="my-6 break-words text-3xl font-bold"><x-dealer-link :dealer-id="$dealer->id" :name="$dealer->name" :public="true" /></h1>
    <dl class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5"><dt class="text-sm text-slate-500">Sales hiện tại</dt><dd class="mt-3 text-3xl font-bold"><a href="{{ route('dealer.sales.index') }}" class="text-red-700 underline">{{ number_format($salesCount) }}</a></dd></div>
        <div class="rounded-xl border border-slate-200 bg-white p-5"><dt class="text-sm text-slate-500">Tổng khai báo</dt><dd class="mt-3 text-3xl font-bold"><a href="{{ route('dealer.customers.index') }}" class="text-red-700 underline">{{ number_format($customerCount) }}</a></dd></div>
        <div class="rounded-xl border border-slate-200 bg-white p-5"><dt class="text-sm text-slate-500">Khách hàng hợp lệ</dt><dd class="mt-3 text-3xl font-bold text-red-700">{{ number_format($standings['all']->score) }}</dd></div>
        <div class="rounded-xl border border-slate-200 bg-white p-5"><dt class="text-sm text-slate-500">Tổng điểm đại lý</dt><dd class="mt-3 text-3xl font-bold text-red-700">{{ number_format($standings['all']->score) }}</dd></div>
    </dl>
    <x-dealer-profile-media :dealer="$dealer" />
    <div class="mt-6"><x-dealer-details :dealer="$dealer" :standing="$standings['all']" /></div>
    <section class="mt-6 grid gap-4 sm:grid-cols-2">
        @foreach (['month' => 'Tháng này'] as $period => $label)
            <div class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-lg font-semibold">{{ $label }}</h2><p class="mt-4 text-3xl font-bold text-red-700">{{ number_format($standings[$period]->score) }} <span class="text-sm font-normal">điểm</span></p><p class="mt-3 text-sm text-slate-500">{{ $standings[$period]->rank ? 'Hạng #'.$standings[$period]->rank : 'Chưa có thứ hạng' }} · Tính theo ngày khai báo đã duyệt</p></div>
        @endforeach
    </section>
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-semibold">Khách hàng gần đây</h2><x-dealer-customer-list :customers="$recentCustomers" /></section>
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-semibold">Thành tích đã công bố</h2><x-dealer-awards :awards="$awards" /></section>
</x-layout>
