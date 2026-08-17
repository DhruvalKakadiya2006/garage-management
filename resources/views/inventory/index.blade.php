<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-cube class="w-6 h-6 text-primary-600" />
                Inventory
            </h2>
            <p class="mt-1 text-sm text-gray-500">Manage spare parts and labour services in one place.</p>
        </div>
    </x-slot>

    <div class="py-6" x-data="{
        tab: window.location.hash === '#labours' ? 'labours' : 'parts',
        setTab(tab) {
            this.tab = tab;
            history.replaceState(null, '', '#' + tab);
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

            <div class="flex gap-1 border-b border-gray-200">
                <button type="button" @click="setTab('parts')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors duration-150"
                        :class="tab === 'parts' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                    <x-heroicon-o-cube class="w-4 h-4" />
                    Spare Parts
                    <span class="text-xs px-1.5 py-0.5 rounded-full"
                          :class="tab === 'parts' ? 'bg-primary-50 text-primary-700' : 'bg-gray-100 text-gray-600'">
                        {{ $spareParts->total() }}
                    </span>
                </button>
                <button type="button" @click="setTab('labours')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors duration-150"
                        :class="tab === 'labours' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                    <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
                    Labour Services
                    <span class="text-xs px-1.5 py-0.5 rounded-full"
                          :class="tab === 'labours' ? 'bg-primary-50 text-primary-700' : 'bg-gray-100 text-gray-600'">
                        {{ $labourMasters->total() }}
                    </span>
                </button>
            </div>

            <div x-show="tab === 'parts'" x-cloak>
                @include('spare_parts._table', ['indexRoute' => 'inventory.index'])
            </div>

            <div x-show="tab === 'labours'" x-cloak>
                @include('labour_masters._table')
            </div>

        </div>
    </div>
</x-app-layout>
