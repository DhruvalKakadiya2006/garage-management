<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-cube class="w-6 h-6 text-primary-600" />
                Spare Parts
            </h2>
            <div class="flex gap-2">
                <x-button variant="success" :href="route('counter-invoices.create')">
                    <x-heroicon-o-plus class="w-4 h-4 mr-1.5" />
                    New Counter Invoice
                </x-button>
                <x-button :href="route('spare-parts.create')">
                    <x-heroicon-o-plus class="w-4 h-4 mr-1.5" />
                    Add Spare Part
                </x-button>
            </div>
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

            <div class="mb-4 flex items-center gap-2">
                <a href="{{ route('spare-parts.index') }}"
                   class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors duration-150 {{ !$lowStock ? 'bg-primary-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                    All Parts
                </a>
                <a href="{{ route('spare-parts.index', ['low_stock' => 1]) }}"
                   class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors duration-150 {{ $lowStock ? 'bg-primary-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                    Low Stock
                </a>
            </div>

            <x-data-table
                :empty="$spareParts->isEmpty()"
                emptyMessage="{{ $lowStock ? 'No low stock parts — everything is above its reorder level.' : 'No spare parts yet. Click \"Add Spare Part\" to create one.' }}"
                emptyIcon="cube"
                :emptyHref="route('spare-parts.create')"
                emptyLabel="Add Spare Part"
                :paginator="$spareParts"
            >
                <x-slot name="header">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Part Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Part Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">MRP</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Labor Charge</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </x-slot>

                @foreach ($spareParts as $part)
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">
                            {{ $part->part_name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $part->part_code }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $part->category ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                            {{ config('garage.currency_symbol') }}{{ number_format($part->mrp, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                            {{ config('garage.currency_symbol') }}{{ number_format($part->labor_charge, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                            {{ config('garage.currency_symbol') }}{{ number_format($part->total_price, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                            @if ($part->current_stock_qty <= 0)
                                <x-badge color="red">{{ $part->current_stock_qty }}</x-badge>
                            @elseif ($part->current_stock_qty <= $part->reorder_level)
                                <x-badge color="amber">{{ $part->current_stock_qty }}</x-badge>
                            @else
                                <span class="text-gray-900">{{ $part->current_stock_qty }}</span>
                            @endif
                        </td>
                        <x-data-table-actions
                            :model="$part"
                            :editRoute="fn ($p) => route('spare-parts.edit', $p)"
                            :deleteRoute="fn ($p) => route('spare-parts.destroy', $p)"
                            confirm="Delete this spare part?"
                        />
                    </tr>
                @endforeach
            </x-data-table>

        </div>
    </div>
</x-app-layout>
