<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-building-storefront class="w-6 h-6 text-primary-600" />
                Suppliers
            </h2>
            <x-button :href="route('suppliers.create')">
                <x-heroicon-o-plus class="w-4 h-4 mr-1.5" />
                Add Supplier
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

            <x-data-table
                :empty="$suppliers->isEmpty()"
                emptyMessage='No suppliers yet. Click "Add Supplier" to create one.'
                emptyIcon="building-storefront"
                :emptyHref="route('suppliers.create')"
                emptyLabel="Add Supplier"
                :paginator="$suppliers"
            >
                <x-slot name="header">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Person</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mobile</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GSTIN</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </x-slot>

                @foreach ($suppliers as $supplier)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $supplier->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $supplier->contact_person ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $supplier->mobile }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $supplier->gstin ?? '-' }}
                        </td>
                        <x-data-table-actions
                            :model="$supplier"
                            :editRoute="fn ($s) => route('suppliers.edit', $s)"
                            :deleteRoute="fn ($s) => route('suppliers.destroy', $s)"
                            confirm="Delete this supplier?"
                        />
                    </tr>
                @endforeach
            </x-data-table>

        </div>
    </div>
</x-app-layout>
