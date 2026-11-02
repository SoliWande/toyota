@php($preview = ($preview ?? false) && app()->environment(['local', 'testing']))
<x-dealer-registration.layout :dealer="$dealer" :title="$dealer->name" :landing="true" :preview="$preview">
    <div data-landing-screen>
        @include('dealers.registration.landing-content')
    </div>
    @if ($preview && $dealer->is_active)
        <div data-step-two-screen hidden>@include('dealers.registration.step-two-content', ['values' => [], 'validationErrors' => [], 'preview' => true])</div>
    @endif
</x-dealer-registration.layout>
