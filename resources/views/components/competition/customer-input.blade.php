@props(['name', 'label', 'type' => 'text', 'value' => null])
@php($fieldValue = old($name, $value))
<div class="wc-staff-field"><label for="{{ $name }}">{{ $label }}</label><input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ is_scalar($fieldValue) ? $fieldValue : '' }}" {{ $attributes }} aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @if ($errors->has($name)) aria-describedby="{{ $name }}-error" @endif>@error($name)<p id="{{ $name }}-error" class="wc-field-error" role="alert">{{ $message }}</p>@enderror</div>
