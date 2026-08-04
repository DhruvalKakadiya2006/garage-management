@props([
    'empty' => false,
    'emptyMessage' => 'No records found.',
    'emptyHref' => null,
    'emptyLabel' => null,
    'emptyIcon' => 'inbox',
    'paginator' => null,
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden']) }}>
    @if ($empty)
        <div class="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center">
            <x-dynamic-component :component="'heroicon-o-' . $emptyIcon" class="w-12 h-12 text-gray-300" />
            <p class="text-sm text-gray-500">{{ $emptyMessage }}</p>
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
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50 border-b border-gray-100">
                    {{ $header }}
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    {{ $slot }}
                </tbody>
            </table>
        </div>

        @if ($paginator && $paginator->hasPages())
            <div class="border-t border-gray-100 px-6 py-3">
                {{ $paginator->links() }}
            </div>
        @endif
    @endif
</div>
