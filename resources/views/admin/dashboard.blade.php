<x-layout title="Admin Dashboard">
    <p class="text-xs font-bold uppercase tracking-widest text-red-600">Không gian quản trị</p>
    <h1 class="mt-3 text-3xl font-bold">Xin chào, {{ auth()->user()->name }}</h1>
    <div class="mt-7 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
        <h2 class="text-lg font-semibold">Toyota Veloz & Hilux</h2>
        <p class="mt-3 leading-7 text-slate-600">Bạn đã đăng nhập với quyền quản trị viên chương trình.</p>
    </div>
</x-layout>
