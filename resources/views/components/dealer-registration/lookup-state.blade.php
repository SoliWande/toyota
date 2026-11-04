@props(['result'])
@php($steps = \App\Services\RegistrationLookupProgress::steps($result))
@if ($steps)
<ol {{ $attributes->class(['dr-lookup-progress']) }} aria-label="Tiến trình đăng ký và Facebook Group" data-lookup-progress>
    @foreach ($steps as $step)
        <li class="dr-lookup-progress-step is-{{ $step['state'] }}" data-step="{{ $step['key'] }}" data-state="{{ $step['state'] }}" @if (in_array($step['state'], ['current', 'warning', 'blocked'], true)) aria-current="step" @endif>
            <span class="dr-lookup-progress-rail" aria-hidden="true">
                @if ($step['icon'] === 'blocked')
                    <span class="dr-lookup-progress-node dr-lookup-progress-blocked">?</span>
                @else
                    <picture class="dr-lookup-progress-node">
                        @if (in_array($step['icon'], ['done', 'current', 'upcoming'], true))
                            <source media="(max-width:767px)" srcset="{{ asset('dealer-registration/lookup-'.$step['icon'].'-mobile.svg') }}">
                        @endif
                        <img src="{{ asset('dealer-registration/lookup-'.$step['icon'].'.svg') }}" width="28" height="28" alt="">
                    </picture>
                @endif
            </span>
            <div class="dr-lookup-progress-text">
                <strong>{{ $step['title'] }}</strong>
                <span class="dr-lookup-progress-sr">{{ $step['status_label'] }}</span>
                <p @class(['dr-lookup-progress-sr' => $step['description_hidden'] ?? false])>{{ $step['description'] }}</p>
            </div>
        </li>
    @endforeach
</ol>
@endif
