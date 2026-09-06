@props([
    'placeholder' => 'Search...',
    'name' => 'search',
    'value' => null,
])

<div class="relative">
    <input 
        type="text" 
        name="{{ $name }}" 
        id="{{ $name }}"
        value="{{ old($name, $value ?? request($name)) }}"
        placeholder="{{ $placeholder }}"
        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
    >
    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
    </div>
</div>
