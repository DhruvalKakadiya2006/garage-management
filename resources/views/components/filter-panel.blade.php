@props([
    'filters' => [],
])

<div class="bg-white rounded-lg border border-gray-200 p-4">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {{ $slot }}
    </div>
</div>
