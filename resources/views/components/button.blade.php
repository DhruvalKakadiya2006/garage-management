@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
])

@php
$baseClasses = 'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md h-10 px-4 py-2 text-sm font-medium shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';

$variantClasses = [
    'primary' => 'bg-primary text-primary-foreground hover:bg-primary/90',
    'secondary' => 'border border-border bg-background hover:bg-accent hover:text-accent-foreground',
    'danger' => 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
    'success' => 'bg-green-600 text-white hover:bg-green-600/90',
    'ghost' => 'hover:bg-accent hover:text-accent-foreground',
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
