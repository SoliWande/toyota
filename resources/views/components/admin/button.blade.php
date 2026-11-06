@props(['variant' => 'dark', 'size' => '42', 'href' => null, 'disabled' => false, 'type' => 'button'])
@php
    $tone = in_array($variant, ['dark', 'secondary', 'danger', 'danger-outline', 'warning', 'warning-soft', 'warning-outline', 'success', 'primary'], true) ? $variant : 'dark';
    $height = in_array((string) $size, ['32', '36', '38', '42', '44'], true) ? (string) $size : '42';
    $classes = ['ad-button', 'ad-button-'.$tone, 'ad-button-'.$height];
@endphp
@if ($href && !$disabled)<a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@elseif ($href)<span aria-disabled="true" {{ $attributes->class($classes) }}>{{ $slot }}</span>
@else<button type="{{ $type }}" @disabled($disabled) {{ $attributes->class($classes) }}>{{ $slot }}</button>@endif
