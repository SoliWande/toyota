@props(['name', 'size' => 20])
@php
    // Original MCP node PNGs, unmodified: Sidebar159:244/251/258/263/271/277/283/288/293/297/306; Topbar159:313.
    // MCP renders flatten onto the node background. An SVG alpha mask restores the glyph for currentColor.
    $glyphs = ['overview' => 20, 'check' => 20, 'search' => 20, 'registration' => 20, 'dealer' => 20,
        'sales' => 20, 'trophy' => 20, 'phone' => 20, 'globe' => 20, 'external' => 16, 'down' => 16, 'bell' => 18];
    $nativeSize = $glyphs[$name] ?? null;
    $background = in_array($name, ['down', 'bell'], true) ? 245 : 255;
    $foreground = match ($name) { 'down', 'external' => 142, 'bell' => 17, default => 94 };
    $factor = 255 / ($background - $foreground);
    $offset = $background / ($background - $foreground);
    $maskId = 'ad-glyph-'.\Illuminate\Support\Str::uuid();
@endphp
@if ($nativeSize)
<svg {{ $attributes->class(['ad-icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $nativeSize }} {{ $nativeSize }}" aria-hidden="true">
    <defs>
        <filter id="{{ $maskId }}-alpha" color-interpolation-filters="sRGB" x="0" y="0" width="100%" height="100%"><feColorMatrix type="matrix" values="0 0 0 0 1 0 0 0 0 1 0 0 0 0 1 -{{ $factor }} 0 0 0 {{ $offset }}" /></filter>
        <mask id="{{ $maskId }}" maskUnits="userSpaceOnUse" x="0" y="0" width="{{ $nativeSize }}" height="{{ $nativeSize }}" style="mask-type:alpha"><image href="{{ asset('admin/icons/'.$name.'.png') }}" width="{{ $nativeSize }}" height="{{ $nativeSize }}" filter="url(#{{ $maskId }}-alpha)" /></mask>
    </defs>
    <rect width="{{ $nativeSize }}" height="{{ $nativeSize }}" fill="currentColor" mask="url(#{{ $maskId }})" />
</svg>
@else
<svg {{ $attributes->class(['ad-icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @if ($name === 'close')<path d="m6 6 12 12M6 18 18 6"/>@elseif ($name === 'tick')<path d="m6 12 4 4 8-8"/>@endif
</svg>
@endif
