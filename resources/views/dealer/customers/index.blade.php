<x-layout :title="'Khách hàng · '.$dealer->name">
    <x-dealer-nav />
    <h1 class="text-3xl font-bold">Khách hàng của đại lý</h1>
    <p class="mt-3 text-sm leading-6 text-slate-500"><x-dealer-link :dealer-id="$dealer->id" :name="$dealer->name" /> · {{ number_format($customers->total()) }} khai báo phù hợp. Khách hàng được giữ tại đại lý ghi nhận khi khai báo.</p>
    <x-admin-feedback />
    <form method="GET" action="{{ route('dealer.customers.index') }}" class="my-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white p-5">
        <div class="min-w-0 grow"><label for="q" class="text-sm font-medium">Tên hoặc số điện thoại khách hàng</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-input" maxlength="255"></div>
        <div><label for="status" class="text-sm font-medium">Trạng thái</label><select id="status" name="status" class="form-input"><option value="">Tất cả</option>@foreach (\App\Enums\SubmissionStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div><button class="button-primary" type="submit">Lọc khách hàng</button>
    </form>
    <section class="rounded-2xl border border-slate-200 bg-white p-5"><x-dealer-customer-list :customers="$customers" /></section>
    <div class="mt-6">{{ $customers->links() }}</div>
</x-layout>
