@props([
    'color' => 'gray',
])

@php
$colorClasses = [
    'green' => 'border-transparent bg-green-100 text-green-800',
    'amber' => 'border-transparent bg-amber-100 text-amber-800',
    'red' => 'border-transparent bg-red-100 text-red-800',
    'gray' => 'border-transparent bg-muted text-muted-foreground',
    'primary' => 'border-transparent bg-primary-100 text-primary-800',
    'purple' => 'border-transparent bg-purple-100 text-purple-800',
];

$classes = 'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold ' . ($colorClasses[$color] ?? $colorClasses['gray']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
