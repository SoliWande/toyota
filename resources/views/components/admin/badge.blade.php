@props(['variant' => 'neutral', 'dot' => true])
@php($tone = in_array($variant, ['pending', 'approved', 'rejected', 'brand', 'info', 'neutral'], true) ? $variant : 'neutral')
<span {{ $attributes->class(['ad-badge', 'ad-badge-'.$tone]) }}>@if ($dot)<i aria-hidden="true"></i>@endif{{ $slot }}</span>
