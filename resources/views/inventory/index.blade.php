<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-cube class="w-6 h-6 text-primary" />
                Inventory
            </h2>
        </div>
    </x-slot>

    <div class="py-6" x-data="{
        tab: {{ Js::from($tab) }},
        setTab(newTab) {
            this.tab = newTab;
            const url = new URL(window.location.href);
            url.searchParams.set('tab', newTab);
            url.hash = '';
            history.replaceState(null, '', url.toString());
        },
    }">
        <div class="max-w-full sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-md">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Search and Filter Form --}}
            <form method="GET" action="{{ route('inventory.index') }}" class="mb-6" x-data>
                <input type="hidden" name="tab" :value="tab">
                
                <div class="space-y-4">
                    {{-- Search Bar --}}
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <div class="relative">
                                <input 
                                    type="text" 
                                    name="search" 
                                    id="search"
                                    value="{{ request('search') }}"
                                    :placeholder="tab === 'parts' ? 'Search spare parts by name, code, or category...' : 'Search labour services by name or category...'"
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <x-button type="submit">
                            <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                            Search
                        </x-button>
                        @if(request()->hasAny(['search', 'category', 'taxable', 'sort']))
                            <x-button variant="secondary" type="button" @click="window.location.href='{{ route('inventory.index') }}?tab=' + tab">
                                Clear
                            </x-button>
                        @endif
                    </div>

                    {{-- Filters --}}
                    <div x-show="tab === 'parts'" x-cloak>
                        <x-filter-panel>
                            <div>
                                <label for="category_parts" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                                <select name="category" id="category_parts" class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All Categories</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>
                                            {{ $cat }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="per_page_parts" class="block text-sm font-medium text-gray-700 mb-1">Items Per Page</label>
                                <select name="per_page" id="per_page_parts" class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="15" {{ request('per_page', 15) == 15 ? 'selected' : '' }}>15</option>
                                    <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                                </select>
                            </div>
                        </x-filter-panel>

                        {{-- Active Filters for Parts --}}
                        <x-active-filters-bar 
                            :filters="[
                                'search' => request('search'),
                                'category' => request('category'),
                            ]"
                            :baseRoute="'inventory.index'"
                        />
                    </div>

                    <div x-show="tab === 'labour'" x-cloak>
                        <x-filter-panel>
                            <div>
                                <label for="taxable" class="block text-sm font-medium text-gray-700 mb-1">Taxable Status</label>
                                <select name="taxable" id="taxable" class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="all" {{ request('taxable', 'all') === 'all' ? 'selected' : '' }}>All Services</option>
                                    <option value="taxable" {{ request('taxable') === 'taxable' ? 'selected' : '' }}>Taxable</option>
                                    <option value="non_taxable" {{ request('taxable') === 'non_taxable' ? 'selected' : '' }}>Non-Taxable</option>
                                </select>
                            </div>

                            <div>
                                <label for="per_page_labour" class="block text-sm font-medium text-gray-700 mb-1">Items Per Page</label>
                                <select name="per_page" id="per_page_labour" class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="15" {{ request('per_page', 15) == 15 ? 'selected' : '' }}>15</option>
                                    <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                                </select>
                            </div>
                        </x-filter-panel>

                        {{-- Active Filters for Labour --}}
                        <x-active-filters-bar 
                            :filters="[
                                'search' => request('search'),
                                'taxable' => request('taxable') !== 'all' ? request('taxable') : null,
                            ]"
                            :baseRoute="'inventory.index'"
                        />
                    </div>
                </div>
            </form>

            <div class="flex gap-1 border-b border-border">
                <button type="button" @click="setTab('parts')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors duration-150"
                        :class="tab === 'parts' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-border'">
                    <x-heroicon-o-cube class="w-4 h-4" />
                    Spare Parts
                    <span class="text-xs px-1.5 py-0.5 rounded-full"
                          :class="tab === 'parts' ? 'bg-accent text-accent-foreground' : 'bg-muted text-muted-foreground'">
                        {{ $spareParts->total() }}
                    </span>
                </button>
                <button type="button" @click="setTab('labour')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors duration-150"
                        :class="tab === 'labour' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-border'">
                    <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
                    Labour Services
                    <span class="text-xs px-1.5 py-0.5 rounded-full"
                          :class="tab === 'labour' ? 'bg-accent text-accent-foreground' : 'bg-muted text-muted-foreground'">
                        {{ $labourMasters->total() }}
                    </span>
                </button>
            </div>

            <div x-show="tab === 'parts'" x-cloak>
                @include('spare_parts._table', ['indexRoute' => 'inventory.index'])
            </div>

            <div x-show="tab === 'labour'" x-cloak>
                @include('labour_masters._table')
            </div>

        </div>
    </div>
</x-app-layout>
