@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full rounded-md border border-gray-300 bg-white h-10 px-3 py-2 text-sm transition-colors placeholder:text-muted-foreground focus:ring-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 shadow-sm']) }}>
