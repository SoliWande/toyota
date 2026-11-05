@props(['leader'])
@php($rank = (int) $leader->rank)
<a class="wc-lb-card wc-lb-place-{{ $rank }} {{ strlen(number_format($leader->score, 0, ',', '.')) > 5 ? 'wc-lb-wide-score' : '' }}" href="{{ route('dealers.competition', $leader->id) }}" aria-label="Hạng {{ $rank }}: {{ $leader->name }}, {{ number_format($leader->score, 0, ',', '.') }} điểm">
    @if ($rank === 1)
        <img class="wc-lb-watermark wc-lb-desktop" src="{{ asset('competition/leaderboard/trophy-watermark-200.svg') }}" width="200" height="200" alt="" aria-hidden="true">
        <img class="wc-lb-watermark wc-lb-mobile" src="{{ asset('competition/leaderboard/trophy-watermark-150.svg') }}" width="150" height="150" alt="" aria-hidden="true">
    @endif
    <span class="wc-lb-medal">
        <img class="wc-lb-desktop" src="{{ asset('competition/leaderboard/trophy-'.($rank === 1 ? 'red' : 'white').'-22.svg') }}" width="22" height="22" alt="" aria-hidden="true">
        <img class="wc-lb-mobile" src="{{ asset('competition/leaderboard/trophy-'.($rank === 1 ? 'red-20' : 'white-18').'.svg') }}" width="{{ $rank === 1 ? 20 : 18 }}" height="{{ $rank === 1 ? 20 : 18 }}" alt="" aria-hidden="true">
    </span>
    <span class="wc-lb-place">Hạng {{ $rank }}</span>
    <span class="wc-lb-dealer-name">{{ $leader->name }}</span>
    <span class="wc-lb-dealer-code">{{ $leader->code }}</span>
    <span class="wc-lb-points"><strong>{{ number_format($leader->score, 0, ',', '.') }}</strong><span>điểm</span></span>
</a>
