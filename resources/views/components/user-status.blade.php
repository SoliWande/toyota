@props(['status'])
@php
    $color = match ($status) {
        \App\Enums\UserStatus::Pending => 'bg-amber-50 text-amber-800',
        \App\Enums\UserStatus::Active => 'bg-green-50 text-green-800',
        \App\Enums\UserStatus::Rejected => 'bg-red-50 text-red-800',
        \App\Enums\UserStatus::Blocked => 'bg-slate-100 text-slate-700',
    };
@endphp
<span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $color }}">{{ $status->label() }}</span>
