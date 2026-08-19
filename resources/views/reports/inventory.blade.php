<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-cube class="w-6 h-6 text-primary" />
                Inventory Report
            </h2>
            <a href="{{ route('reports.index') }}" 
               class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-800">
                <x-heroicon-o-arrow-left class="w-4 h-4" />
                Back to Reports
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Date Range Filter -->
            <x-date-range-filter 
                :fromDate="$fromDate" 
                :toDate="$toDate" 
                :action="route('reports.inventory')" 
            />

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-xl p-5">
                    <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Total Parts</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $inventory->count() }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-xl p-5 border-l-4 border-red-500">
                    <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Low Stock Alerts</p>
                    <p class="text-2xl font-bold {{ $lowStockCount > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $lowStockCount }}</p>
                    <p class="text-xs text-gray-500 mt-1">At or below reorder level</p>
                </div>
                <div class="bg-white shadow-sm rounded-xl p-5">
                    <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Total Units in Stock</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($totalUnits) }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-xl p-5">
                    <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Stock Value (at cost)</p>
                    <p class="text-2xl font-bold text-gray-900">{{ config('garage.currency_symbol') }}{{ number_format($totalStockValue, 2) }}</p>
                </div>
            </div>

            <!-- Parts table, sorted by times used (fast movers first) -->
            <div class="bg-white shadow-sm rounded-xl p-6">
                <h3 class="font-semibold text-gray-900 mb-1">Stock Levels & Usage</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Parts used between 
                    <strong>{{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }}</strong> and 
                    <strong>{{ \Carbon\Carbon::parse($toDate)->format('d M Y') }}</strong>,
                    sorted by units used (fast movers first). Parts at or below reorder level are flagged red.
                </p>

                @if($inventory->isEmpty())
                    <p class="text-sm text-gray-500">No spare parts found.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Part</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Category</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">In Stock</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">Reorder Level</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">Cost ({{ config('garage.currency_symbol') }})</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">Stock Value ({{ config('garage.currency_symbol') }})</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">Units Used</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($inventory as $part)
                                <tr class="{{ $part['low_stock'] ? 'bg-red-50' : '' }} hover:bg-muted/50">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900">{{ $part['part_name'] }}</div>
                                        <div class="text-xs text-gray-500">{{ $part['part_code'] }}</div>
                                        @if($part['low_stock'])
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-red-700">
                                                <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" />
                                                Low Stock
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600 capitalize">{{ $part['category'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right font-medium {{ $part['current_stock_qty'] <= 0 ? 'text-red-600' : 'text-gray-900' }}">
                                        {{ $part['current_stock_qty'] }} {{ $part['unit'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-gray-600">{{ $part['reorder_level'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-gray-600">{{ number_format($part['purchase_price'], 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-gray-600">{{ number_format($part['stock_value'], 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right font-medium">
                                        @if($part['times_used'] > 0)
                                            <span class="text-primary">{{ $part['times_used'] }}</span>
                                        @else
                                            <span class="text-gray-400">0</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
