@props(['status'])
@php
    $color = match ($status) {
        \App\Enums\SubmissionStatus::Pending => 'bg-amber-50 text-amber-800',
        \App\Enums\SubmissionStatus::Approved => 'bg-green-50 text-green-700',
        \App\Enums\SubmissionStatus::Rejected => 'bg-red-50 text-red-700',
    };
@endphp
<span {{ $attributes->class(['inline-block rounded-full px-3 py-1 text-xs font-semibold', $color]) }}>{{ $status->label() }}</span>
