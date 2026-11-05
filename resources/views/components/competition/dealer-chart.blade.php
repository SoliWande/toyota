@props(['months'])
@php
    $maximum = max(1, ...array_column($months, 'score'));
@endphp
@foreach (['desktop' => [600, 220, 150, 40, 16], 'mobile' => [318, 170, 110, 16, 14]] as $viewport => [$width, $height, $barMax, $gap, $font])
<svg class="wc-d-chart-svg wc-d-{{ $viewport }}" viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="Số thành viên đã được duyệt theo tháng">
@foreach ($months as $month)
    @php
        $barWidth = ($width - $gap * 2) / 3;
        $x = $loop->index * ($barWidth + $gap);
        $barHeight = $month['score'] / $maximum * $barMax;
        $bottom = $height - 30;
    @endphp
    <g @class(['wc-d-bar', 'wc-d-current-bar' => $loop->last])><title>{{ $month['period'] }}: {{ $month['score'] }} thành viên</title>
        <text x="{{ $x + $barWidth / 2 }}" y="{{ $bottom - $barHeight - 12 }}" text-anchor="middle" font-size="{{ $font }}">{{ number_format($month['score'], 0, ',', '.') }}</text>
        <rect x="{{ $x }}" y="{{ $bottom - $barHeight }}" width="{{ $barWidth }}" height="{{ $barHeight }}" rx="10" />
        <text class="wc-d-month-label" x="{{ $x + $barWidth / 2 }}" y="{{ $height - 7 }}" text-anchor="middle" font-size="{{ $viewport === 'desktop' ? 13 : 12 }}">{{ $month['label'] }}</text>
    </g>
@endforeach
</svg>
@endforeach
