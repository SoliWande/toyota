@props(['name', 'label', 'type' => 'text'])
<div>
    <label for="{{ $name }}" class="block text-sm font-medium">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name) }}" @endif
        {{ $attributes->class(['form-input', 'border-red-500' => $errors->has($name)]) }}
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        @if ($errors->has($name)) aria-describedby="{{ $name }}-error" @endif>
    @error($name)
        <p id="{{ $name }}-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
    @enderror
</div>
