<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-book-open class="w-6 h-6 text-primary" />
                Vehicle Catalog
            </h2>
            <x-button :href="route('vehicle-brands.create')">
                <x-heroicon-o-plus class="w-4 h-4" />
                Add Brand
            </x-button>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full sm:px-6 lg:px-8"
             x-data="mergeAlpineData(tableSelection({ bulkEndpoint: '{{ route('vehicle-brands.bulk-delete') }}', bulkExportEndpoint: '{{ route('vehicle-brands.bulk-export') }}', rowAttr: 'data-brand-id', modalName: 'bulk-delete-confirm-brands' }), vehicleCatalog())">

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
            <x-table-selection-bar
                :bulk-endpoint="route('vehicle-brands.bulk-delete')"
                :bulk-export-endpoint="route('vehicle-brands.bulk-export')"
                modal-name="bulk-delete-confirm-brands"
                bulkConfirmMessage="Brands with models under them will be skipped."
            >
                <x-slot name="single">
                    <x-button type="button" @click="$dispatch('open-modal', 'bulk-delete-confirm')" class="flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                        <x-heroicon-o-trash class="w-4 h-4" />
                        Delete
                    </x-button>
                    <x-bulk-delete-confirm
                        :name="'bulk-delete-confirm'"
                        :title="'Delete this brand?'"
                        :confirm-label="'Delete'"
                        :message="'Brands with models under them will be skipped.'"
                    />
                </x-slot>
            </x-table-selection-bar>

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
                        <th class="align-middle w-10 px-6 py-3 text-left"><x-table-select-all /></th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Brand</th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Models</th>
                        <th class="align-middle w-10 px-6 py-3 text-right"></th>
                    </tr>
                </x-slot>

                @foreach ($brands as $brand)
                    <tr class="group hover:bg-muted/50">
                        <td class="align-middle px-6 py-2 whitespace-nowrap">
                            <x-table-checkbox :id="$brand->id" attr="data-brand-id" />
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $brand->name }}
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-600">
                            {{ $brand->models_count }} model(s)
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-right">
                            <button type="button" @click="toggleExpand('{{ $brand->id }}')" title="View models"
                                    class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition-colors duration-150 hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                <x-heroicon-o-chevron-down class="w-5 h-5 transition-transform duration-150"
                                                           x-bind:class="expandedId === '{{ $brand->id }}' ? 'rotate-180' : ''" />
                            </button>
                        </td>
                    </tr>
                    <tr x-show="expandedId === '{{ $brand->id }}'" class="bg-muted/60">
                        <td colspan="4" class="align-middle px-6 py-2">
                            <div x-data="tableSelection({ bulkEndpoint: '{{ route('vehicle-models.bulk-delete') }}', bulkExportEndpoint: '{{ route('vehicle-models.bulk-export') }}', rowAttr: 'data-model-id', modalName: 'bulk-delete-confirm-models-{{ $brand->id }}' })">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                        Models under {{ $brand->name }} ({{ $brand->models->count() }})
                                    </div>
                                    <label class="flex items-center gap-2 text-xs text-muted-foreground cursor-pointer">
                                        <x-table-select-all />
                                        Select all models
                                    </label>
                                </div>

                                <x-table-selection-bar
                                    :bulk-endpoint="route('vehicle-models.bulk-delete')"
                                    :bulk-export-endpoint="route('vehicle-models.bulk-export')"
                                    modal-name="bulk-delete-confirm-models-{{ $brand->id }}"
                                    bulkConfirmMessage="Models linked to existing vehicles will be skipped."
                                >
                                    <x-slot name="single">
                                        <x-button variant="secondary" type="button"
                                                  @click="window.location.href = '{{ url('vehicle-models') }}' + '/' + singleId + '/edit'">
                                            <x-heroicon-o-pencil-square class="w-4 h-4" />
                                            Edit
                                        </x-button>
                                    </x-slot>
                                </x-table-selection-bar>

                                @forelse ($brand->models as $model)
                                    <div class="flex items-center gap-3 py-2 border-b border-border last:border-0">
                                        <x-table-checkbox :id="$model->id" attr="data-model-id" />
                                        <span class="text-sm font-medium text-gray-900">{{ $model->name }}</span>
                                        @if ($model->battery_type)
                                            <x-badge color="gray">{{ ucwords(str_replace('-', ' ', $model->battery_type)) }}</x-badge>
                                        @endif
                                    </div>
                                @empty
                                    <div class="text-sm text-gray-500">No models yet. Select this brand and click "Add Model".</div>
                                @endforelse
                            </div>
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

            return {
                expandedId: '',
                catalogData: catalogData,

                get selectedBrand() {
                    return this.catalogData.find(b => b.id === this.singleId) || null;
                },

                toggleExpand(id) {
                    this.expandedId = this.expandedId === String(id) ? '' : String(id);
                },

                goAddModel() {
                    if (this.singleId) {
                        window.location.href = modelsCreate + '?brand_id=' + this.singleId;
                    }
                },

                goEditBrand() {
                    if (this.singleId) {
                        window.location.href = brandsBase + '/' + this.singleId + '/edit';
                    }
                },
            };
        }
    </script>
</x-app-layout>
