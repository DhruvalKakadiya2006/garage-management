<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-users class="w-6 h-6 text-primary" />
                Customers
            </h2>
            <div class="flex items-center gap-2">
                <x-button variant="secondary" :href="route('import-export.index', ['entity' => 'customers'])">
                    <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                    Import
                </x-button>
                <x-dropdown align="left" width="56">
                    <x-slot name="trigger">
                        <x-button variant="secondary" type="button">
                            <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                            Export
                            <x-heroicon-o-chevron-down class="w-4 h-4" />
                        </x-button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('import-export.export', ['entity' => 'customers', 'format' => 'csv'])">
                            <span class="inline-flex items-center gap-2">
                                <x-heroicon-o-document-text class="w-4 h-4" />
                                Export CSV
                            </span>
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('import-export.export', ['entity' => 'customers', 'format' => 'xlsx'])">
                            <span class="inline-flex items-center gap-2">
                                <x-heroicon-o-document-text class="w-4 h-4" />
                                Export Excel
                            </span>
                        </x-dropdown-link>
                        <div class="my-1 border-t border-border"></div>
                        <x-dropdown-link :href="route('import-export.template', ['entity' => 'customers', 'format' => 'csv'])">
                            <span class="inline-flex items-center gap-2">
                                <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
                                Download Import Template (CSV)
                            </span>
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('import-export.template', ['entity' => 'customers', 'format' => 'xlsx'])">
                            <span class="inline-flex items-center gap-2">
                                <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
                                Download Import Template (Excel)
                            </span>
                        </x-dropdown-link>
                    </x-slot>
                </x-dropdown>
                <x-button :href="route('customers.create')">
                    <x-heroicon-o-plus class="w-4 h-4" />
                    Add Customer
                </x-button>
            </div>
        </div>
    </x-slot>

    @php
        $customersData = $customers->getCollection()->map(fn ($c) => [
            'id' => (string) $c->id,
            'display_name' => $c->display_name,
            'vehicles' => $c->vehicles->map(fn ($v) => [
                'id' => (string) $v->id,
                'registration_no' => $v->registration_no,
                'is_non_rto' => (bool) $v->is_non_rto,
                'brand' => $v->vehicleModel?->vehicleBrand?->name,
                'model' => $v->vehicleModel?->name,
                'color' => $v->color,
            ])->values(),
        ])->values();
    @endphp

    <div class="py-6">
        <div class="max-w-full sm:px-6 lg:px-8" x-data="customerList()">

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
            <div x-show="selectedId" x-transition.opacity.duration.150
                 class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 shadow-sm">
                <span class="text-sm font-medium text-primary-800 mr-auto">
                    <span class="text-primary">Selected:</span>
                    <span x-text="selectedCustomer ? selectedCustomer.display_name : ''" class="font-semibold"></span>
                </span>
                <x-button type="button" @click="createJobCard()">
                    <x-heroicon-o-wrench-screwdriver class="w-4 h-4" />
                    Create Job Card
                </x-button>
                <x-button variant="secondary" type="button" @click="addVehicle()">
                    <x-heroicon-o-plus class="w-4 h-4" />
                    Add Vehicle
                </x-button>
                <x-button variant="secondary" type="button" @click="editSelected()">
                    <x-heroicon-o-pencil-square class="w-4 h-4" />
                    Edit
                </x-button>
                <form :action="'{{ url('customers') }}' + '/' + selectedId" method="POST"
                      onsubmit="return confirm('Delete this customer? Related vehicles and documents will be protected.');"
                      class="inline">
                    @csrf
                    @method('DELETE')
                    <x-button variant="danger" type="submit">
                        <x-heroicon-o-trash class="w-4 h-4" />
                        Delete
                    </x-button>
                </form>
            </div>

            <x-data-table
                :empty="$customers->isEmpty()"
                emptyMessage='No customers yet. Click "Add Customer" to create one.'
                emptyIcon="users"
                :emptyHref="route('customers.create')"
                emptyLabel="Add Customer"
                :paginator="$customers"
            >
                <x-slot name="header">
                    <tr>
                        <th class="w-10 px-6 py-3 text-left"></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Display Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Mobile</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Outstanding</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Total Visits</th>
                        <th class="w-10 px-6 py-3 text-right"></th>
                    </tr>
                </x-slot>

                @foreach ($customers as $customer)
                    <tr class="group hover:bg-muted/50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="radio" name="customer" value="{{ $customer->id }}" x-model="selectedId"
                                   class="h-4 w-4 rounded-full border-input text-primary focus:ring-ring cursor-pointer">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="{{ route('customers.show', $customer) }}"
                               class="text-primary hover:text-primary-700 transition-colors duration-150">
                                {{ $customer->display_name }}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $customer->mobile }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm {{ $customer->outstanding > 0 ? 'font-medium text-red-600' : 'text-gray-600' }}">
                            {{ config('garage.currency_symbol') }}{{ number_format($customer->outstanding, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $customer->job_cards_count + $customer->counter_invoices_count }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <button type="button" @click="toggleExpand('{{ $customer->id }}')"
                                    title="{{ $customer->vehicles->count() }} vehicle(s)"
                                    class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition-colors duration-150 hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                <x-heroicon-o-chevron-down class="w-5 h-5 transition-transform duration-150"
                                                           x-bind:class="expandedId === '{{ $customer->id }}' ? 'rotate-180' : ''" />
                            </button>
                        </td>
                    </tr>
                    <tr x-show="expandedId === '{{ $customer->id }}'" class="bg-muted/60">
                        <td colspan="6" class="px-6 py-4">
                            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-2">
                                Vehicles ({{ $customer->vehicles->count() }})
                            </div>
                            @forelse ($customer->vehicles as $vehicle)
                                <div class="flex items-center gap-3 py-2 border-b border-border last:border-0">
                                    @if ($vehicle->is_non_rto)
                                        <x-badge color="amber">NON-RTO</x-badge>
                                    @else
                                        <span class="text-xs font-medium text-gray-900">{{ $vehicle->registration_no }}</span>
                                    @endif
                                    <span class="text-xs text-gray-600">
                                        {{ $vehicle->vehicleModel?->vehicleBrand?->name }} {{ $vehicle->vehicleModel?->name }}
                                    </span>
                                    @if ($vehicle->color)
                                        <span class="text-xs text-gray-400">{{ $vehicle->color }}</span>
                                    @endif
                                </div>
                            @empty
                                <div class="text-xs text-gray-500">No vehicles registered for this customer.</div>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </x-data-table>

            {{-- Vehicle picker for customers with multiple vehicles --}}
            <x-modal name="pick-vehicle" maxWidth="lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Select Vehicle</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        <span class="font-medium text-gray-700" x-text="selectedCustomer ? selectedCustomer.display_name : ''"></span>
                        has multiple vehicles. Choose one to create a Job Card.
                    </p>
                    <div class="space-y-2">
                        <template x-for="v in (selectedCustomer ? selectedCustomer.vehicles : [])" :key="v.id">
                            <button type="button" @click="goToJobCard(v.id)"
                                    class="w-full flex items-center justify-between rounded-lg border border-border px-4 py-3 text-left text-sm transition-colors duration-150 hover:border-primary hover:bg-accent">
                                <span class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900" x-text="v.registration_no || 'NON-RTO'"></span>
                                    <template x-if="v.is_non_rto">
                                        <span class="inline-flex items-center rounded-full bg-amber-100 text-amber-800 px-2.5 py-0.5 text-xs font-medium">NON-RTO</span>
                                    </template>
                                </span>
                                <span class="text-gray-500" x-text="[v.brand, v.model].filter(Boolean).join(' ') || '—'"></span>
                            </button>
                        </template>
                    </div>
                    <div class="mt-5 flex justify-end">
                        <x-button variant="secondary" type="button" @click="$dispatch('close-modal', 'pick-vehicle')">
                            Cancel
                        </x-button>
                    </div>
                </div>
            </x-modal>

        </div>
    </div>

    <script>
        function customerList() {
            const customersData = @json($customersData);

            const customersBase = '{{ url('customers') }}';
            const vehiclesBase = '{{ url('vehicles') }}';
            const jobCardsCreate = '{{ route('job-cards.create') }}';

            return {
                selectedId: '',
                expandedId: '',
                customersData: customersData,

                get selectedCustomer() {
                    return this.customersData.find(c => c.id === this.selectedId) || null;
                },

                toggleExpand(id) {
                    this.expandedId = this.expandedId === String(id) ? '' : String(id);
                },

                createJobCard() {
                    const vehicles = this.selectedCustomer ? this.selectedCustomer.vehicles : [];
                    if (vehicles.length === 1) {
                        this.goToJobCard(vehicles[0].id);
                        return;
                    }
                    if (vehicles.length > 1) {
                        this.$dispatch('open-modal', 'pick-vehicle');
                        return;
                    }
                    alert('This customer has no vehicles yet. Add a vehicle first.');
                },

                goToJobCard(vehicleId) {
                    window.location.href = jobCardsCreate + '?customer_id=' + this.selectedId + '&vehicle_id=' + vehicleId;
                },

                addVehicle() {
                    if (this.selectedId) {
                        window.location.href = vehiclesBase + '/create?customer_id=' + this.selectedId;
                    }
                },

                editSelected() {
                    if (this.selectedId) {
                        window.location.href = customersBase + '/' + this.selectedId + '/edit';
                    }
                },
            };
        }
    </script>
</x-app-layout>
