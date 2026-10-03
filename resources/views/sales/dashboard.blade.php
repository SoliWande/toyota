<x-layout title="Sales Dashboard">
    <p class="text-xs font-bold uppercase tracking-widest text-red-600">Không gian Sales</p>
    <h1 class="mt-3 text-3xl font-bold">Xin chào, {{ auth()->user()->name }}</h1>
    <div class="mt-7 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
        <p class="text-sm font-medium text-green-700">Tài khoản đã được duyệt</p>
        <h2 class="mt-3 text-lg font-semibold">{{ auth()->user()->dealer->name }}</h2>
        <p class="mt-3 leading-7 text-slate-600">Chào mừng bạn đến với chương trình cộng đồng Toyota Veloz & Hilux.</p>
    </div>
</x-layout>
