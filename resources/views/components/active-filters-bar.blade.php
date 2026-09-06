@props([
    'filters' => [],
    'baseRoute' => null,
])

@php
    $activeFilters = collect($filters)->filter(fn($value, $key) => !is_null($value) && $value !== '' && $value !== 'all');
    $hasActiveFilters = $activeFilters->isNotEmpty();
@endphp

@if($hasActiveFilters)
<div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-sm font-medium text-blue-900">Active Filters:</span>
            @foreach($activeFilters as $key => $value)
                <span class="inline-flex items-center gap-1 px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                    <span class="font-medium">{{ ucwords(str_replace('_', ' ', $key)) }}:</span>
                    <span>{{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</span>
                    <a href="{{ request()->fullUrlWithQuery([$key => null]) }}" class="ml-1 text-blue-600 hover:text-blue-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                </span>
            @endforeach
        </div>
        @if($baseRoute)
            <a href="{{ route($baseRoute) }}" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                Clear All
            </a>
        @endif
    </div>
</div>
@endif
