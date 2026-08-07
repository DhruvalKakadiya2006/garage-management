<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-truck class="w-6 h-6 text-primary-600" />
                Vehicles
            </h2>
            <x-button :href="route('vehicles.create')">
                <x-heroicon-o-plus class="w-4 h-4 mr-1.5" />
                Add Vehicle
            </x-button>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

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

            <x-data-table
                :empty="$vehicles->isEmpty()"
                emptyMessage='No vehicles yet. Click "Add Vehicle" to create one.'
                emptyIcon="truck"
                :emptyHref="route('vehicles.create')"
                emptyLabel="Add Vehicle"
                :paginator="$vehicles"
            >
                <x-slot name="header">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registration No.</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Brand</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Model</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RTO Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </x-slot>

                @foreach ($vehicles as $vehicle)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $vehicle->registration_no }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $vehicle->customer->display_name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $vehicle->vehicle_brand ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $vehicle->model ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $vehicle->is_non_rto ? 'Non-RTO' : 'RTO' }}
                        </td>
                        <x-data-table-actions
                            :model="$vehicle"
                            :editRoute="fn ($v) => route('vehicles.edit', $v)"
                            :deleteRoute="fn ($v) => route('vehicles.destroy', $v)"
                            confirm="Delete this vehicle? This cannot be undone."
                        />
                    </tr>
                @endforeach
            </x-data-table>

        </div>
    </div>
</x-app-layout>
