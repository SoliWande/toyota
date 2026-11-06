@props(['id'])
<div id="{{ $id }}" {{ $attributes->class(['ad-toast']) }} data-admin-toast role="status" hidden><span class="ad-toast-icon"><x-admin.icon name="tick" :size="14" /></span><span>{{ $slot }}</span>@isset($action)<span class="ad-toast-action">{{ $action }}</span>@endisset</div>
