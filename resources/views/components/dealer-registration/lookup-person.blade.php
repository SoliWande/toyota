@props(['result', 'values' => [], 'd12' => false])
@php
    $kind = $result['kind'];
    $labels = ['draft' => 'Chưa hoàn tất', 'supplement' => 'Cần bổ sung', 'not_requested' => 'Chờ vào group',
        'requested' => 'Chờ duyệt', 'member' => 'Thành viên', 'blocked' => 'Chưa xác nhận', 'found' => 'Chưa xác nhận'];
@endphp
<div class="{{ $d12 ? 'dr-d12-person' : 'dr-lookup-person' }} dr-lookup-person-row" data-lookup-person>
    <x-dealer-registration.lookup-avatar :name="$result['masked_name']" :class="$d12 ? 'dr-d12-avatar' : 'dr-lookup-avatar'" />
    <div class="dr-lookup-person-info">
        <h3>{{ $result['masked_name'] }}</h3>
        <p>{{ $result['vehicle'] }}@if (!empty($values['license_plate'])) · {{ $values['license_plate'] }}@endif</p>
    </div>
    <span class="dr-status-chip dr-lookup-chip-{{ $kind }}{{ $d12 ? ' dr-d12-status' : '' }}" data-lookup-badge>{{ $labels[$kind] }}</span>
</div>
