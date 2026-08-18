@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
])

@php
$baseClasses = 'inline-flex items-center justify-center px-4 py-2 rounded-lg font-medium text-sm transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';

$variantClasses = [
    'primary' => 'bg-primary-600 hover:bg-primary-700 text-white shadow-sm',
    'secondary' => 'bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 shadow-sm',
    'danger' => 'bg-red-600 hover:bg-red-700 text-white shadow-sm',
    'success' => 'bg-green-600 hover:bg-green-700 text-white shadow-sm',
    'ghost' => 'bg-transparent hover:bg-primary-50 text-primary-600',
];

$classes = $baseClasses . ' ' . ($variantClasses[$variant] ?? $variantClasses['primary']);

$isLink = $href !== null || $attributes->has('x-bind:href') || $attributes->has(':href');
@endphp

@if($isLink)
    <a @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
