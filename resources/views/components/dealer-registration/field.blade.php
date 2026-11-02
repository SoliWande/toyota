@props(['name', 'label', 'type' => 'text', 'required' => false, 'value' => '', 'help' => null, 'messages' => [], 'prefix' => 'dr', 'liveErrors' => false])
@php($id = $prefix.'-'.$name)
<div class="dr-field" data-field="{{ $name }}">
    <label for="{{ $id }}">{{ $label }} @if ($required)<span aria-hidden="true">*</span>@endif</label>
    <input {{ $attributes->class(['dr-input']) }} id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}" @required($required) aria-invalid="{{ count($messages) ? 'true' : 'false' }}" aria-describedby="{{ $id }}-error{{ $help ? ' '.$id.'-help' : '' }}">
    @if ($help)<p id="{{ $id }}-help" class="dr-help">{{ $help }}</p>@endif
    <p id="{{ $id }}-error" class="dr-error" data-error-for="{{ $name }}" @if ($liveErrors) aria-live="polite" aria-atomic="true" @endif @if (! count($messages)) hidden @endif>{{ implode(' ', $messages) }}</p>
</div>
