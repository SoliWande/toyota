@props(['label' => 'Xem thể lệ đầy đủ', 'newTab' => true])
<a {{ $attributes }} href="{{ route('campaign.rules') }}" @if ($newTab) target="_blank" rel="noopener noreferrer" @endif>{{ $label }}@if ($newTab)<span class="sr-only"> (mở trong tab mới)</span>@endif</a>
