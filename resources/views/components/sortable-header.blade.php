@props([
    'column' => null,
    'label' => null,
    'currentSort' => null,
    'currentDirection' => 'asc',
])

@php
    $isSorted = $currentSort === $column;
    $nextDirection = $isSorted && $currentDirection === 'asc' ? 'desc' : 'asc';
    $sortUrl = request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDirection]);
@endphp

<th {{ $attributes->merge(['class' => 'px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider']) }}>
    @if($column)
        <a href="{{ $sortUrl }}" class="group inline-flex items-center gap-1 hover:text-gray-700">
            <span>{{ $label }}</span>
            @if($isSorted)
                @if($currentDirection === 'asc')
                    <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                    </svg>
                @else
                    <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                @endif
            @else
                <svg class="w-4 h-4 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                </svg>
            @endif
        </a>
    @else
        {{ $label }}
    @endif
</th>
