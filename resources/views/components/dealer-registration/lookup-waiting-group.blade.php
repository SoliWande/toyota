@props(['result', 'values' => []])
<div class="dr-card dr-d12" data-lookup-kind="not_requested">
    <div class="dr-d12-information">
        <div class="dr-d12-person">
            <x-dealer-registration.lookup-avatar :name="$result['masked_name']" class="dr-d12-avatar" />
            <div><h3>{{ $result['masked_name'] }}</h3><p>{{ $result['vehicle'] }} @if (!empty($values['license_plate'])) · {{ $values['license_plate'] }} @endif</p></div>
            <span class="dr-d12-status">Chờ vào group</span>
        </div>
        <x-dealer-registration.lookup-state :result="$result" class="dr-lookup-progress-d12" />
    </div>
    <div class="dr-d12-action">
        <h3>Bước tiếp: vào group</h3>
        <p>Khi xin vào group, trả lời số điện thoại {{ $values['phone'] ?? '' }} và biển số {{ $values['license_plate'] ?? '' }}.</p>
        <a class="dr-button" href="{{ config('dealer-registration.group_url') }}" target="_blank" rel="noopener noreferrer">Vào group Facebook</a>
    </div>
</div>
