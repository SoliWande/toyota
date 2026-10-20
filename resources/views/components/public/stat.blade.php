@props(['value', 'label', 'detail', 'href' => null])
<div class="px-3 py-6 sm:px-8 sm:py-8">
    <dt class="text-xs font-medium text-stone-600 sm:text-sm">{{ $label }}</dt>
    <dd class="mt-2 text-3xl font-semibold tracking-tight sm:text-5xl">@if ($href)<a href="{{ $href }}" aria-label="Xem danh sách {{ $label }}" class="inline-block rounded text-red-700 underline decoration-red-200 underline-offset-4 hover:decoration-red-700 focus-visible:outline-red-600">{{ number_format($value, 0, ',', '.') }}</a>@else{{ number_format($value, 0, ',', '.') }}@endif</dd>
    <p class="mt-2 hidden text-xs text-stone-500 sm:block">{{ $detail }}</p>
</div>
