@props([
    'empty' => false,
    'emptyMessage' => 'No records found.',
    'emptyHref' => null,
    'emptyLabel' => null,
    'emptyIcon' => 'inbox',
    'paginator' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-border bg-card shadow-sm overflow-hidden']) }}>
    @if ($empty)
        <div class="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center">
            <x-dynamic-component :component="'heroicon-o-' . $emptyIcon" class="w-12 h-12 text-muted-foreground" />
            <p class="text-sm text-muted-foreground">{{ $emptyMessage }}</p>
            @if ($emptyHref)
                <div class="mt-1">
                    <x-button :href="$emptyHref">
                        {{ $emptyLabel ?: 'Add record' }}
                    </x-button>
                </div>
            @endif
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-border">
                <thead class="bg-muted/50 border-b border-border">
                    {{ $header }}
                </thead>
                <tbody class="bg-card divide-y divide-border">
                    {{ $slot }}
                </tbody>
            </table>
        </div>

        @if ($paginator && $paginator->hasPages())
            <div class="border-t border-border px-6 py-3">
                {{ $paginator->links() }}
            </div>
        @endif
    @endif
</div>
