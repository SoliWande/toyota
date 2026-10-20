<x-public.layout title="Đăng ký khách hàng">
    <section class="public-container public-section">
        <h1 class="public-heading">Chưa thể gửi đăng ký</h1>
        <p role="alert" class="mt-5 leading-7">{{ $message }}</p>
        <a href="{{ route('dealers.profile', ['slug' => request()->route('slug')]) }}#registration" class="button-primary mt-6 inline-flex">Quay lại đăng ký</a>
    </section>
</x-public.layout>
