@props([
    'name',
    'label' => null,
    'options' => [],
    'required' => false,
    'value' => '',
    'placeholder' => null,
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
    
    <select 
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $required ? 'required' : '' }}
        {{ $attributes->merge([
            'class' => 'block w-full rounded-md border border-input bg-background h-10 px-3 py-2 text-sm transition-colors focus:ring-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2'
        ]) }}
    >
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        
        @if($slot->isEmpty())
            @foreach($options as $optionValue => $optionLabel)
                <option 
                    value="{{ $optionValue }}" 
                    {{ old($name, $value) == $optionValue ? 'selected' : '' }}
                >
                    {{ $optionLabel }}
                </option>
            @endforeach
        @else
            {{ $slot }}
        @endif
    </select>
    
    @error($name)
        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
    @enderror
</div>
