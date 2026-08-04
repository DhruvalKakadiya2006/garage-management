<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-users class="w-6 h-6 text-primary-600" />
                Customers
            </h2>
            <x-button :href="route('customers.create')">
                <x-heroicon-o-plus class="w-4 h-4 mr-1.5" />
                Add Customer
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
                :empty="$customers->isEmpty()"
                emptyMessage='No customers yet. Click "Add Customer" to create one.'
                emptyIcon="users"
                :emptyHref="route('customers.create')"
                emptyLabel="Add Customer"
                :paginator="$customers"
            >
                <x-slot name="header">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mobile</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">City</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </x-slot>

                @foreach ($customers as $customer)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $customer->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $customer->mobile }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $customer->city ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <x-badge :color="match ($customer->customer_type) {
                                'regular' => 'green',
                                'fleet' => 'blue',
                                'dealer' => 'purple',
                                default => 'gray',
                            }">
                                {{ ucwords(str_replace('_', ' ', $customer->customer_type)) }}
                            </x-badge>
                        </td>
                        <x-data-table-actions
                            :model="$customer"
                            :editRoute="fn ($c) => route('customers.edit', $c)"
                            :deleteRoute="fn ($c) => route('customers.destroy', $c)"
                            confirm="Delete this customer? Related vehicles and documents will be protected."
                        />
                    </tr>
                @endforeach
            </x-data-table>

        </div>
    </div>
</x-app-layout>
