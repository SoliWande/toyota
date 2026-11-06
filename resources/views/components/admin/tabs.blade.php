@props(['items', 'active'])
<nav {{ $attributes->class(['ad-tabs']) }}>
    @foreach ($items as $item)
        @if (filled($item['href'] ?? null))<a class="ad-tab" href="{{ $item['href'] }}" @if ($active === $item['key']) aria-current="page" @endif>{{ $item['label'] }}@if (isset($item['count']))<span @class(['ad-tab-count', 'ad-tab-alert' => $item['alert'] ?? false])>{{ number_format($item['count'], 0, ',', '.') }}</span>@endif</a>
        @else<span class="ad-tab" @if ($active === $item['key']) aria-current="page" @else aria-disabled="true" @endif>{{ $item['label'] }}@if (isset($item['count']))<span @class(['ad-tab-count', 'ad-tab-alert' => $item['alert'] ?? false])>{{ number_format($item['count'], 0, ',', '.') }}</span>@endif</span>@endif
    @endforeach
</nav>
