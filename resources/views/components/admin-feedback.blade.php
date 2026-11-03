@props(['exclude' => []])
@php($visibleErrors = collect($errors->getMessages())->except($exclude)->flatten()->all())
@if (session('success'))
    <p role="status" class="mb-5 rounded-xl bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</p>
@endif
@if ($visibleErrors)
    <div role="alert" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-800">
        <ul class="list-inside list-disc space-y-1">
            @foreach ($visibleErrors as $error) <li>{{ $error }}</li> @endforeach
        </ul>
    </div>
@endif
