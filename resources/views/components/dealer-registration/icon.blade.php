@props(['name', 'size' => 18])
<img {{ $attributes->class(['dr-icon']) }} src="{{ asset('dealer-registration/'.$name.'.png') }}" width="{{ $size }}" height="{{ $size }}" alt="" aria-hidden="true">
