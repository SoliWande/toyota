@props(['customers'])
<ul class="wc-manager-customer-list">
    @foreach ($customers as $customer)
        @php
            $digits = preg_replace('/\D/', '', $customer->phone ?? '');
            $maskedPhone = strlen($digits) >= 9 && strlen($digits) <= 15 ? '••• ••• '.substr($digits, -3) : null;
            $badgeStyle = $customer->status === \App\Enums\SubmissionStatus::Approved ? 'active' : $customer->status->value;
        @endphp
        <li class="wc-customer-row">
            <div class="wc-manager-customer-copy">
                <a href="{{ route('dealer.customers.show', $customer) }}"><strong>{{ $customer->customer_name }}</strong></a>
                @if ($customer->sales)<small><x-sales-link :sales="$customer->sales" /></small>@endif
                @if ($maskedPhone)<small>{{ $maskedPhone }}</small>@endif
                <small><time datetime="{{ $customer->submitted_at->toIso8601String() }}">{{ $customer->submitted_at->format('d/m/Y H:i') }}</time></small>
            </div>
            <x-submission-status :status="$customer->status" class="wc-staff-status wc-staff-status-{{ $badgeStyle }}" />
        </li>
    @endforeach
</ul>
