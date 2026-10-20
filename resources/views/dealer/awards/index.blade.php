<x-layout :title="'Thành tích · '.$dealer->name">
    <x-dealer-nav />
    <h1 class="text-3xl font-bold">Thành tích đại lý</h1>
    <p class="mt-3 break-words text-sm text-slate-500"><x-dealer-link :dealer-id="$dealer->id" :name="$dealer->name" /> · Kết quả tuần/tháng đã được công bố</p>
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5"><x-dealer-awards :awards="$awards" /></section>
    <div class="mt-6">{{ $awards->links() }}</div>
</x-layout>
