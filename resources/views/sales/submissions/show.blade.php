<x-competition.account-layout title="Chi tiết khách" :staff="true">
    <x-competition.staff-customer-detail :submission="$submission" :progress="$progress" :lookup-url="$lookupUrl" :back-url="route('sales.submissions.index')" />
</x-competition.account-layout>
