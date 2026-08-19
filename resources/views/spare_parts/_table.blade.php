@php
    $indexRoute = $indexRoute ?? 'inventory.index';
@endphp

<div x-data="tableSelection({ bulkEndpoint: '{{ route('spare-parts.bulk-delete') }}', modalName: 'bulk-delete-confirm-parts' })">

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <a href="{{ route($indexRoute) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors duration-150 {{ !$lowStock ? 'bg-primary text-white' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground' }}">
                All Parts
            </a>
            <a href="{{ route($indexRoute, ['low_stock' => 1]) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors duration-150 {{ $lowStock ? 'bg-primary text-white' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground' }}">
                Low Stock
            </a>
        </div>
        <x-button :href="route('spare-parts.create')">
            <x-heroicon-o-plus class="w-4 h-4" />
            Add Spare Part
        </x-button>
    </div>

    <x-table-selection-bar
        :bulk-endpoint="route('spare-parts.bulk-delete')"
        modal-name="bulk-delete-confirm-parts"
        bulkConfirmMessage="Parts linked to existing job cards or invoices will be skipped."
    >
        <x-slot name="single">
            <x-button variant="secondary" type="button"
                      @click="window.location.href = '{{ url('spare-parts') }}' + '/' + singleId + '/edit'">
                <x-heroicon-o-pencil-square class="w-4 h-4" />
                Edit
            </x-button>
        </x-slot>
    </x-table-selection-bar>

    <x-data-table
        :empty="$spareParts->isEmpty()"
        emptyMessage="{{ $lowStock ? 'No low stock parts - everything is above its reorder level.' : 'No spare parts yet. Click Add Spare Part to create one.' }}"
        emptyIcon="cube"
        :emptyHref="route('spare-parts.create')"
        emptyLabel="Add Spare Part"
        :paginator="$spareParts"
    >
        <x-slot name="header">
            <tr>
                <th class="w-10 px-6 py-3 text-left"><x-table-select-all /></th>
                <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Part Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Part Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Category</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">MRP</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Labor Charge</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Total</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-muted-foreground uppercase tracking-wider">Current Stock</th>
            </tr>
        </x-slot>

        @foreach ($spareParts as $part)
            <tr class="hover:bg-muted/50">
                <td class="px-6 py-4 whitespace-nowrap">
                    <x-table-checkbox :id="$part->id" />
                </td>
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
            </tr>
        @endforeach
    </x-data-table>
</div>