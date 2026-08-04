@foreach ($navItems as $item)
    <x-nav-link :href="route($item['route'])"
                :active="request()->routeIs($item['pattern'])"
                icon="{{ $item['icon'] }}"
                :collapsible="$collapsible">
        {{ $item['label'] }}
    </x-nav-link>
@endforeach
