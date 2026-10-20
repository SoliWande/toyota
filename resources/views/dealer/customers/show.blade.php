<x-layout :title="'Khách hàng · '.$submission->customer_name">
    <x-dealer-nav />
    <a href="{{ route('dealer.customers.index') }}" class="text-sm text-slate-500 hover:text-red-700">← Khách hàng của đại lý</a>
    <h1 class="my-6 break-words text-3xl font-bold">{{ $submission->customer_name }}</h1>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-8">
        <x-submission-status :status="$submission->status" />
        <dl class="mt-6 space-y-5 text-sm">
            <div><dt class="text-slate-500">Sales khai báo</dt><dd class="mt-1 break-words"><x-sales-link :sales="$submission->sales" /></dd></div>
            <div><dt class="text-slate-500">Đại lý ghi nhận</dt><dd class="mt-1 break-words"><x-dealer-link :dealer-id="$dealer->id" :name="$dealer->name" /></dd></div>
            <div><dt class="text-slate-500">Điện thoại khách hàng</dt><dd class="mt-1">{{ $submission->phone ?: 'Chưa cung cấp' }}</dd></div>
            <div><dt class="text-slate-500">Facebook</dt><dd class="mt-1 break-all">@if ($submission->facebook_url_normalized)<a href="{{ $submission->facebook_url_normalized }}" target="_blank" rel="noopener noreferrer" class="text-red-700 hover:underline">{{ $submission->facebook_url }}</a>@endif</dd></div>
            <x-submission-address-details :submission="$submission" />
            <div><dt class="text-slate-500">Thời điểm khai báo</dt><dd class="mt-1">{{ $submission->submitted_at->format('d/m/Y H:i') }}</dd></div>
            @if ($submission->reviewed_at)<div><dt class="text-slate-500">Thời điểm xét duyệt</dt><dd class="mt-1">{{ $submission->reviewed_at->format('d/m/Y H:i') }}</dd></div>@endif
        </dl>
        <x-submission-vehicle-details :submission="$submission" />
    </section>
</x-layout>
