@props(['active', 'icon' => null, 'collapsible' => false])

@php
$baseClasses = 'group flex items-center mx-2 my-0.5 py-2 rounded-lg text-sm transition duration-150 ease-in-out focus:outline-none';
$stateClasses = ($active ?? false)
    ? 'bg-primary-50 text-primary-700 font-medium'
    : 'text-gray-600 hover:bg-primary-50 hover:text-primary-800 focus:text-primary-800 focus:bg-primary-50';
@endphp

@if ($collapsible)
    <a {{ $attributes->merge(['class' => trim("$baseClasses $stateClasses")]) }} :class="collapsed ? 'justify-center px-2' : 'px-3'">
        @if ($icon)
            @if ($active)
                <x-dynamic-component :component="'heroicon-s-' . $icon" class="w-5 h-5 shrink-0" />
            @else
                <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-5 h-5 shrink-0" />
            @endif
        @endif

        <span x-show="!collapsed" class="ms-3 whitespace-nowrap">{{ $slot }}</span>
    </a>
@else
    <a {{ $attributes->merge(['class' => trim("$baseClasses $stateClasses px-3")]) }}>
        @if ($icon)
            @if ($active)
                <x-dynamic-component :component="'heroicon-s-' . $icon" class="w-5 h-5 shrink-0" />
            @else
                <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-5 h-5 shrink-0" />
            @endif
        @endif

        <span class="ms-3 whitespace-nowrap">{{ $slot }}</span>
    </a>
@endif
