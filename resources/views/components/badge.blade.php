@props([
    'color' => 'gray',
])

@php
$colorClasses = [
    'green' => 'bg-green-100 text-green-800',
    'amber' => 'bg-amber-100 text-amber-800',
    'red' => 'bg-red-100 text-red-800',
    'gray' => 'bg-gray-100 text-gray-800',
    'primary' => 'bg-primary-100 text-primary-800',
    'purple' => 'bg-purple-100 text-purple-800',
];

$classes = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ' . ($colorClasses[$color] ?? $colorClasses['gray']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
