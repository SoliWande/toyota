<x-layout :title="'Sales · '.$dealer->name">
    <x-dealer-nav />
    <h1 class="text-3xl font-bold">Sales hiện tại</h1>
    <p class="mt-3 text-sm text-slate-500"><x-dealer-link :dealer-id="$dealer->id" :name="$dealer->name" /> · {{ number_format($sales->total()) }} tài khoản phù hợp</p>
    <x-admin-feedback />
    <form method="GET" action="{{ route('dealer.sales.index') }}" class="my-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white p-5">
        <div class="min-w-0 grow"><label for="q" class="text-sm font-medium">Tên, email hoặc điện thoại</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-input" maxlength="255"></div>
        <div><label for="status" class="text-sm font-medium">Trạng thái</label><select id="status" name="status" class="form-input"><option value="">Tất cả</option>@foreach (\App\Enums\UserStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div><button class="button-primary" type="submit">Lọc Sales</button>
    </form>
    <ul class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white p-5">
        @forelse ($sales as $person)
            <li class="flex flex-wrap items-center justify-between gap-4 py-4"><div class="flex min-w-0 items-center gap-4"><x-sales-avatar :sales-id="$person->id" :has-avatar="(bool) $person->avatar_path" /><div class="min-w-0"><a href="{{ route('dealer.sales.show', $person) }}" class="break-words font-semibold text-red-700 hover:underline">{{ $person->name }}</a><p class="mt-1 break-all text-sm text-slate-500">{{ $person->email }}</p></div></div><x-user-status :status="$person->status" /></li>
        @empty
            <li class="py-6 text-sm text-slate-500">Không có Sales phù hợp.</li>
        @endforelse
    </ul>
    <div class="mt-6">{{ $sales->links() }}</div>
</x-layout>
