@props(['value', 'label', 'detail'])
<div class="px-3 py-6 sm:px-8 sm:py-8">
    <dt class="text-xs font-medium text-stone-600 sm:text-sm">{{ $label }}</dt>
    <dd class="mt-2 text-3xl font-semibold tracking-tight sm:text-5xl">{{ number_format($value, 0, ',', '.') }}</dd>
    <p class="mt-2 hidden text-xs text-stone-500 sm:block">{{ $detail }}</p>
</div>
