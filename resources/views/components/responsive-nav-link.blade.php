@props(['active', 'icon' => null])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-primary-600 text-start text-base font-medium text-primary bg-primary-50 focus:outline-none focus:text-primary-800 focus:bg-primary-100 focus:border-primary-700 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-muted hover:border-border focus:outline-none focus:text-gray-800 focus:bg-muted focus:border-border transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)
        <span class="inline-flex items-center">
            @if ($active)
                <x-dynamic-component :component="'heroicon-s-' . $icon" class="w-5 h-5 me-2 -ms-1" />
            @else
                <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-5 h-5 me-2 -ms-1" />
            @endif
        </span>
    @endif

    {{ $slot }}
</a>
