<x-layout :title="'Sales · '.$sales->name">
    <x-dealer-nav />
    <a href="{{ route('dealer.sales.index') }}" class="text-sm text-slate-500 hover:text-red-700">← Sales hiện tại</a>
    <div class="my-6 flex flex-wrap items-center gap-4"><x-sales-avatar :sales-id="$sales->id" :has-avatar="(bool) $sales->avatar_path" /><h1 class="break-words text-3xl font-bold">{{ $sales->name }}</h1></div>
    @if ($sales->status === \App\Enums\UserStatus::Active && $sales->public_slug)
        <a href="{{ route('sales.public.show', ['slug' => $sales->public_slug]) }}" class="mb-6 inline-block rounded text-sm font-semibold text-red-700 hover:underline focus-visible:outline-red-600">Xem profile công khai</a>
    @endif
    <section class="rounded-2xl border border-slate-200 bg-white p-6"><x-user-status :status="$sales->status" /><dl class="mt-6 space-y-5 text-sm"><div><dt class="text-slate-500">Đại lý hiện tại</dt><dd class="mt-1 break-words"><x-dealer-link :dealer-id="$dealer->id" :name="$dealer->name" /></dd></div><div><dt class="text-slate-500">Email</dt><dd class="mt-1 break-all">{{ $sales->email }}</dd></div><div><dt class="text-slate-500">Điện thoại</dt><dd class="mt-1">{{ $sales->phone ?: 'Chưa cung cấp' }}</dd></div></dl></section>
</x-layout>
