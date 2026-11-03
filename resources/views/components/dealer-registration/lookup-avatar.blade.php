@props(['name'])
@php
    $parts = preg_split('/\s+/u', trim($name));
    $initials = mb_substr($parts[0], 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '');
@endphp
<span {{ $attributes }} aria-hidden="true">{{ $initials }}</span>
