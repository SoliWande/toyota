@props(['url' => null, 'label', 'group' => false])
@php
    $parts = is_string($url) ? parse_url($url) : false;
    $valid = is_array($parts) && ($parts['scheme'] ?? '') === 'https' && ! empty($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass']);
    if ($group) $valid = $valid && in_array(strtolower($parts['host'] ?? ''), ['facebook.com', 'www.facebook.com'], true) && str_starts_with($parts['path'] ?? '', '/groups/');
@endphp
@if ($valid)
    <a {{ $attributes }} href="{{ $url }}" @if ($group) target="_blank" rel="noopener noreferrer" @endif>{{ $label }}</a>
@else
    <span {{ $attributes->class(['dr-unavailable']) }} role="link" aria-disabled="true" title="Thông tin đang được cập nhật">{{ $label }}<span class="sr-only"> — chưa khả dụng</span></span>
@endif
