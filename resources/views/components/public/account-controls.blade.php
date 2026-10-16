@auth
    <a href="{{ route(auth()->user()->homeRoute()) }}" class="py-3 text-sm font-semibold">{{ auth()->user()->status !== \App\Enums\UserStatus::Active ? 'Trạng thái tài khoản' : (auth()->user()->role === \App\Enums\UserRole::Admin ? 'Dashboard quản trị' : 'Dashboard đại lý') }}</a>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="py-3 text-sm font-semibold text-stone-600 hover:text-red-700">Đăng xuất</button>
    </form>
@else
    <a href="{{ route('login') }}" class="py-3 text-sm font-semibold">Đăng nhập</a>
    <a href="{{ route('register') }}" class="public-button">Đăng ký đại lý <x-public.arrow /></a>
@endauth
