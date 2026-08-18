@props([
    'title' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-border bg-card text-card-foreground shadow-sm p-6']) }}>
    @if($title)
        <h3 class="mb-4 text-base font-semibold text-card-foreground">{{ $title }}</h3>
    @endif

    {{ $slot }}
</div>
