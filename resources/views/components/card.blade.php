@props([
    'title' => null,
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl shadow-sm border border-gray-100 p-6']) }}>
    @if($title)
        <h3 class="mb-4 text-base font-semibold text-gray-900">{{ $title }}</h3>
    @endif

    {{ $slot }}
</div>
