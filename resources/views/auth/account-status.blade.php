<x-layout title="Trạng thái tài khoản">
    <section class="mx-auto max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
        @if (session('success'))
            <p role="status" class="mb-6 rounded-xl bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</p>
        @endif
        <p class="mb-3 text-sm text-slate-500">Xin chào {{ auth()->user()->name }}</p>
        @switch(auth()->user()->status)
            @case(\App\Enums\UserStatus::Pending)
                <h1 class="text-2xl font-bold">Tài khoản đang chờ duyệt</h1>
                <p class="mt-4 leading-7 text-slate-600">Quản trị viên sẽ kiểm tra thông tin đăng ký của bạn. Bạn có thể sử dụng hệ thống sau khi tài khoản được duyệt.</p>
                @break
            @case(\App\Enums\UserStatus::Rejected)
                <h1 class="text-2xl font-bold">Đăng ký chưa được chấp nhận</h1>
                <p class="mt-4 leading-7 text-slate-600">Tài khoản chưa được phép sử dụng hệ thống. Vui lòng liên hệ quản trị viên chương trình để được hỗ trợ.</p>
                @break
            @case(\App\Enums\UserStatus::Blocked)
                <h1 class="text-2xl font-bold">Tài khoản đã bị khóa</h1>
                <p class="mt-4 leading-7 text-slate-600">Quyền sử dụng hệ thống của bạn đang bị khóa. Vui lòng liên hệ quản trị viên chương trình để được hỗ trợ.</p>
                @break
        @endswitch
        <a href="{{ route('home') }}" class="mt-7 inline-block text-sm font-semibold text-red-600 hover:underline">Về trang chủ</a>
    </section>
</x-layout>
