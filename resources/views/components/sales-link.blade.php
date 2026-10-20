@props(['sales', 'fallback' => 'Khách tự đăng ký qua QR'])
@php
    $viewer = auth()->user();
    $active = $viewer?->status === \App\Enums\UserStatus::Active;
    $url = null;
    if ($sales) {
        if ($active && $viewer->role === \App\Enums\UserRole::Admin) {
            $url = route('admin.sales.show', $sales->id);
        } elseif ($active && $viewer->role === \App\Enums\UserRole::Dealer && $viewer->dealer_id === $sales->dealer_id) {
            $url = route('dealer.sales.show', $sales->id);
        } elseif ($sales->status === \App\Enums\UserStatus::Active && $sales->public_slug) {
            $url = route('sales.public.show', ['slug' => $sales->public_slug]);
        }
    }
@endphp
@if ($url)
    <a href="{{ $url }}" {{ $attributes->class(['rounded hover:text-red-700 hover:underline focus-visible:outline-red-600']) }}>{{ $sales->name }}</a>
@else
    <span {{ $attributes }}>{{ $sales?->name ?? $fallback }}</span>
@endif
