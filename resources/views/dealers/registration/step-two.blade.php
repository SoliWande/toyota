<x-dealer-registration.layout :dealer="$dealer" title="Thông tin xe & địa chỉ" :preview="$preview ?? false" :d02="empty($validationErrors)">
    @include('dealers.registration.step-two-content', ['values' => $values ?? [], 'validationErrors' => $validationErrors ?? [], 'preview' => $preview ?? false])
</x-dealer-registration.layout>
