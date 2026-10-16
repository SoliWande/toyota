@php
    $isAdmin = auth()->user()->role === \App\Enums\UserRole::Admin;
    $links = $isAdmin
        ? ['admin.dashboard' => 'Dashboard quản trị', 'admin.dealers.index' => 'Quản lý đại lý', 'admin.dealer-accounts.index' => 'Tài khoản đại lý', 'admin.submissions.index' => 'Duyệt khai báo', 'admin.awards.index' => 'Vinh danh đại lý']
        : ['dealer.dashboard' => 'Dashboard đại lý', 'dealer.submissions.index' => 'Khai báo của đại lý', 'dealer.submissions.create' => 'Tạo khai báo'];
    $links['profile.edit'] = 'Hồ sơ';
@endphp
<div class="flex w-full flex-wrap items-center gap-x-5 gap-y-1 border-t border-slate-100 pt-3" aria-label="{{ $isAdmin ? 'Chức năng quản trị' : 'Chức năng đại lý' }}">
    @foreach ($links as $route => $label)
        <a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif class="py-2 text-sm font-medium {{ request()->routeIs($route) ? 'text-red-600' : 'text-slate-600 hover:text-red-600' }}">{{ $label }}</a>
    @endforeach
</div>
