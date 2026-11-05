@props(['name', 'label', 'type' => 'text'])
<div class="wc-auth-field"><label for="{{ $name }}">{{ $label }}</label>
    <div @class(['wc-auth-password' => $type === 'password'])>
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if ($type !== 'password') value="{{ is_string(old($name)) ? old($name) : '' }}" @endif {{ $attributes }} aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @if ($errors->has($name)) aria-describedby="{{ $name }}-error" @endif>
        @if ($type === 'password')<button type="button" class="wc-auth-toggle" data-account-password aria-controls="{{ $name }}" aria-pressed="false" aria-label="Hiện {{ mb_strtolower($label) }}" hidden>Hiện</button>@endif
    </div>
    @error($name)<p id="{{ $name }}-error" class="wc-auth-field-error" role="alert">{{ $message }}</p>@enderror
</div>
