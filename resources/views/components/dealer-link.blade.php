@props(['dealerId' => null, 'name', 'public' => false])
@php
    $viewer = auth()->user();
    $active = $viewer?->status === \App\Enums\UserStatus::Active;
    $url = $dealerId ? route('dealers.show', $dealerId) : null;
    if ($url && ! $public && $active) {
        if ($viewer->role === \App\Enums\UserRole::Admin) {
            $url = route('admin.dealers.show', $dealerId);
        } elseif ($viewer->role === \App\Enums\UserRole::Dealer && $viewer->dealer_id === (int) $dealerId) {
            $url = route('dealer.dashboard');
        }
    }
@endphp
@if ($url)
    <a href="{{ $url }}" {{ $attributes->class(['rounded hover:text-red-700 hover:underline focus-visible:outline-red-600']) }}>{{ $name }}</a>
@else
    <span {{ $attributes }}>{{ $name }}</span>
@endif
