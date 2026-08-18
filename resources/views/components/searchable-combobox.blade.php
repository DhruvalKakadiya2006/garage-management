@props([
    'name' => '',
    'endpoint',
    'initialId' => '',
    'initialLabel' => '',
    'placeholder' => '-- Search --',
    'searchParam' => 'q',
    'minChars' => 2,
    'required' => false,
    'bare' => false,
    'id' => '',
])

@php
    $config = [
        'endpoint' => $endpoint,
        'name' => $bare ? '' : $name,
        'initialId' => $initialId === '' || $initialId === null ? '' : (string) $initialId,
        'initialLabel' => (string) $initialLabel,
        'placeholder' => $placeholder,
        'searchParam' => $searchParam,
        'minChars' => $minChars,
        'required' => $required,
    ];
@endphp

<div x-data="searchableCombobox(@js($config))" class="relative"
     {{ $attributes->merge(['class' => '']) }}>
    <input type="text" x-model="query" @input="onInput()" @keydown="onKeydown($event)" @focus="onFocus()"
           :aria-expanded="open"
           data-combobox-input
           placeholder="{{ $placeholder }}"
           autocomplete="off"
           class="block w-full rounded-md border-gray-300 pr-8 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm transition-colors duration-150">

    @if(!$bare)
        <input type="hidden" :name="fullName" :value="selected ? selected.id : ''">
    @endif

    <template x-if="selected">
        <button type="button" @click="clear()" title="Clear selection"
                class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center rounded-md p-1 text-gray-400 hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
            <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
        </button>
    </template>

    <template x-if="open">
        <ul x-transition.opacity.duration.120
            class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
            <template x-if="busy">
                <li class="px-3 py-2 text-xs text-gray-500">Searching…</li>
            </template>
            <template x-for="(result, i) in results" :key="result.id">
                <li>
                    <button type="button" @click="select(result)" @mouseenter="highlighted = i"
                            class="block w-full px-3 py-2 text-left text-sm transition-colors duration-100"
                            :class="highlighted === i ? 'bg-primary-50 text-primary-800' : 'text-gray-800'"
                            x-text="result.label"></button>
                </li>
            </template>
        </ul>
    </template>
</div>