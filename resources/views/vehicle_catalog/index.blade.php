<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-book-open class="w-6 h-6 text-primary-600" />
                Vehicle Catalog
            </h2>
            <x-button :href="route('vehicle-brands.create')">
                <x-heroicon-o-plus class="w-4 h-4 mr-1.5" />
                Add Brand
            </x-button>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full sm:px-6 lg:px-8" x-data="vehicleCatalog()">

            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-md">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Contextual action bar --}}
            <div x-show="selectedBrandId || selectedModelId" x-transition.opacity.duration.150
                 class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 shadow-sm">

                {{-- Model actions --}}
                <template x-if="selectedModelId">
                    <div class="flex flex-wrap items-center gap-3 w-full">
                        <span class="text-sm font-medium text-primary-800 mr-auto">
                            <span class="text-primary-500">Selected Model:</span>
                            <span class="font-semibold" x-text="selectedModel ? selectedModel.name + ' (' + selectedModel.brand_name + ')' : ''"></span>
                        </span>
                        <x-button variant="secondary" type="button" @click="goEditModel()">
                            <x-heroicon-o-pencil-square class="w-4 h-4 mr-1.5" />
                            Edit
                        </x-button>
                        <form :action="'{{ url('vehicle-models') }}' + '/' + selectedModelId" method="POST"
                              onsubmit="return confirm('Delete this model?');" class="inline">
                            @csrf
                            @method('DELETE')
                            <x-button variant="danger" type="submit">
                                <x-heroicon-o-trash class="w-4 h-4 mr-1.5" />
                                Delete
                            </x-button>
                        </form>
                    </div>
                </template>

                {{-- Brand actions --}}
                <template x-if="selectedBrandId && !selectedModelId">
                    <div class="flex flex-wrap items-center gap-3 w-full">
                        <span class="text-sm font-medium text-primary-800 mr-auto">
                            <span class="text-primary-500">Selected Brand:</span>
                            <span class="font-semibold" x-text="selectedBrand ? selectedBrand.name : ''"></span>
                        </span>
                        <x-button type="button" @click="goAddModel()">
                            <x-heroicon-o-plus class="w-4 h-4 mr-1.5" />
                            Add Model
                        </x-button>
                        <x-button variant="secondary" type="button" @click="goEditBrand()">
                            <x-heroicon-o-pencil-square class="w-4 h-4 mr-1.5" />
                            Edit
                        </x-button>
                        <form :action="'{{ url('vehicle-brands') }}' + '/' + selectedBrandId" method="POST"
                              onsubmit="return confirm('Delete this brand? Models under it must be deleted first.');" class="inline">
                            @csrf
                            @method('DELETE')
                            <x-button variant="danger" type="submit">
                                <x-heroicon-o-trash class="w-4 h-4 mr-1.5" />
                                Delete
                            </x-button>
                        </form>
                    </div>
                </template>
            </div>

            <x-data-table
                :empty="$brands->isEmpty()"
                emptyMessage='No brands yet. Click "Add Brand" to create one.'
                emptyIcon="book-open"
                :emptyHref="route('vehicle-brands.create')"
                emptyLabel="Add Brand"
                :paginator="$brands"
            >
                <x-slot name="header">
                    <tr>
                        <th class="w-10 px-6 py-3 text-left"></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Brand</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Models</th>
                        <th class="w-10 px-6 py-3 text-right"></th>
                    </tr>
                </x-slot>

                @foreach ($brands as $brand)
                    <tr class="group hover:bg-muted/50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="radio" name="vehicle_brand" value="{{ $brand->id }}" x-model="selectedBrandId"
                                   @change="selectedModelId = ''"
                                   class="h-4 w-4 rounded-full border-gray-300 text-primary-600 focus:ring-primary-500 cursor-pointer">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs font-medium text-gray-900">
                            {{ $brand->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">
                            {{ $brand->models_count }} model(s)
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <button type="button" @click="toggleExpand('{{ $brand->id }}')" title="View models"
                                    class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition-colors duration-150 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
                                <x-heroicon-o-chevron-down class="w-5 h-5 transition-transform duration-150"
                                                           x-bind:class="expandedId === '{{ $brand->id }}' ? 'rotate-180' : ''" />
                            </button>
                        </td>
                    </tr>
                    <tr x-show="expandedId === '{{ $brand->id }}'" class="bg-gray-50/60">
                        <td colspan="4" class="px-6 py-4">
                            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-2">
                                Models under {{ $brand->name }} ({{ $brand->models->count() }})
                            </div>
                            @forelse ($brand->models as $model)
                                <div class="flex items-center gap-3 py-2 border-b border-gray-100 last:border-0">
                                    <input type="radio" name="vehicle_model" value="{{ $model->id }}" x-model="selectedModelId"
                                           @change="selectedBrandId = ''"
                                           class="h-4 w-4 rounded-full border-gray-300 text-primary-600 focus:ring-primary-500 cursor-pointer">
                                    <span class="text-sm font-medium text-gray-900">{{ $model->name }}</span>
                                    @if ($model->battery_type)
                                        <x-badge color="gray">{{ ucwords(str_replace('-', ' ', $model->battery_type)) }}</x-badge>
                                    @endif
                                </div>
                            @empty
                                <div class="text-sm text-gray-500">No models yet. Select this brand and click "Add Model".</div>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </x-data-table>

        </div>
    </div>

    <script>
        function vehicleCatalog() {
            const catalogData = @json($catalogData);

            const brandsBase = '{{ url('vehicle-brands') }}';
            const modelsCreate = '{{ url('vehicle-models/create') }}';
            const modelsBase = '{{ url('vehicle-models') }}';

            return {
                selectedBrandId: '',
                selectedModelId: '',
                expandedId: '',
                catalogData: catalogData,

                get selectedBrand() {
                    return this.catalogData.find(b => b.id === this.selectedBrandId) || null;
                },

                get selectedModel() {
                    for (const b of this.catalogData) {
                        const m = b.models.find(m => m.id === this.selectedModelId);
                        if (m) return { ...m, brand_name: b.name };
                    }
                    return null;
                },

                toggleExpand(id) {
                    this.expandedId = this.expandedId === String(id) ? '' : String(id);
                },

                goAddModel() {
                    if (this.selectedBrandId) {
                        window.location.href = modelsCreate + '?brand_id=' + this.selectedBrandId;
                    }
                },

                goEditBrand() {
                    if (this.selectedBrandId) {
                        window.location.href = brandsBase + '/' + this.selectedBrandId + '/edit';
                    }
                },

                goEditModel() {
                    if (this.selectedModelId) {
                        window.location.href = modelsBase + '/' + this.selectedModelId + '/edit';
                    }
                },
            };
        }
    </script>
</x-app-layout>
