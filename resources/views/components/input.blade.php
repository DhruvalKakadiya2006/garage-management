@props([
    'name',
    'label' => null,
    'type' => 'text',
    'required' => false,
    'value' => '',
])

<div class="space-y-1">
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
            {{ $label }}
            @if($required)
                <span class="text-red-600">*</span>
            @endif
        </label>
    @endif
    
    @if ($type === 'textarea')
        <textarea
            name="{{ $name }}"
            id="{{ $name }}"
            rows="{{ $attributes->has('rows') ? $attributes->get('rows') : 3 }}"
            {{ $required ? 'required' : '' }}
            {{ $attributes->except(['rows'])->merge([
                'class' => 'block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm transition-colors duration-150'
            ]) }}
        >{{ old($name, $value) }}</textarea>
    @else
        <input 
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $name }}"
            value="{{ old($name, $value) }}"
            {{ $required ? 'required' : '' }}
            {{ $attributes->merge([
                'class' => 'block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm transition-colors duration-150'
            ]) }}
        >
    @endif
    
    @error($name)
        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
    @enderror
</div>
