@props(['result', 'dealer', 'values' => []])
<div class="dr-card dr-d12" data-lookup-kind="not_requested">
    <div class="dr-d12-information">
        <x-dealer-registration.lookup-person :result="$result" :values="$values" :d12="true" />
        <x-dealer-registration.lookup-state :result="$result" class="dr-lookup-progress-d12" />
    </div>
    <div class="dr-d12-action" data-lookup-sidebox>
        <x-dealer-registration.lookup-next :result="$result" :dealer="$dealer" :values="$values" />
    </div>
</div>
