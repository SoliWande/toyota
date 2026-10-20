<nav aria-label="Quản lý đại lý" class="mb-6 flex flex-wrap gap-2 text-sm font-semibold">
    @foreach (['dealer.dashboard' => 'Tổng quan', 'dealer.sales.index' => 'Sales hiện tại', 'dealer.customers.index' => 'Khách hàng', 'dealer.awards.index' => 'Thành tích'] as $route => $label)
        <a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif class="rounded-lg border border-slate-200 px-4 py-3 focus-visible:outline-red-600 {{ request()->routeIs($route) ? 'bg-red-700 text-white' : 'bg-white text-slate-700 hover:bg-slate-100' }}">{{ $label }}</a>
    @endforeach
</nav>
