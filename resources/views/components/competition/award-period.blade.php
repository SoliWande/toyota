@props(['award'])
{{ $award->period_type->label() }} {{ $award->period_type === \App\Enums\AwardPeriod::Monthly ? $award->period_start->format('n/Y') : $award->period_start->format('d/m/Y').' – '.$award->period_end->subDay()->format('d/m/Y') }}
