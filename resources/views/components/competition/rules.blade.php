@props(['campaignDates' => '[Chờ TMV]'])
@php
    $questions = require resource_path('content/competition-faq.php');
    if ($campaignDates !== '[Chờ TMV]') {
        $questions[1][1] = $campaignDates;
    }
@endphp
<section id="the-le" class="wc-container wc-section wc-rules" aria-labelledby="rules-heading">
    <div class="wc-rules-heading"><x-competition.heading eyebrow="MINH BẠCH & CÔNG BẰNG" id="rules-heading">Thể lệ chương trình</x-competition.heading><a href="{{ route('campaign.rules') }}">Xem thể lệ đầy đủ <span aria-hidden="true">→</span></a></div>
    <div class="wc-rules-columns">
        @foreach (array_chunk($questions, 6) as $column)
            <div class="wc-accordion">
                @foreach ($column as [$question, $answer])
                    <details><summary>{{ $question }}<span class="wc-accordion-toggle"><x-competition.icon name="plus" :size="16" /></span></summary><div class="wc-accordion-answer"><p>{{ $answer }}</p>@if ($question === 'Giải thưởng')<a href="{{ route('awards.index') }}">Xem lịch sử vinh danh</a>@elseif ($question === 'Liên hệ hỗ trợ')<a href="{{ route('dealers.index') }}">Đại lý tham gia</a>@endif</div></details>
                @endforeach
            </div>
        @endforeach
    </div>
</section>
