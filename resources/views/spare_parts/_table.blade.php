@php
    $indexRoute = $indexRoute ?? 'inventory.index';
@endphp

<div x-data="tableSelection({ bulkEndpoint: '{{ route('spare-parts.bulk-delete') }}', bulkExportEndpoint: '{{ route('spare-parts.bulk-export') }}', modalName: 'bulk-delete-confirm-parts' })">

    <div class="mb-4 flex flex-wrap items-center justify-end gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <x-button variant="secondary" type="button" @click="$dispatch('open-modal', 'import-csv-spare-parts')">
                <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                Import CSV
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
                    <x-dropdown-link :href="route('import-export.export', array_merge(['entity' => 'spare_parts'], request()->query()))">
                        <span class="inline-flex items-center gap-2">
                            <x-heroicon-o-document-text class="w-4 h-4" />
                            Export All (CSV)
                        </span>
                    </x-dropdown-link>
                    <div class="my-1 border-t border-border"></div>
                    <x-dropdown-link :href="route('import-export.template', ['entity' => 'spare_parts'])">
                        <span class="inline-flex items-center gap-2">
                            <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
                            Download Import Template (CSV)
                        </span>
                    </x-dropdown-link>
                </x-slot>
            </x-dropdown>
            <x-button :href="route('spare-parts.create')">
                <x-heroicon-o-plus class="w-4 h-4" />
                Add Spare Part
            </x-button>
        </div>
    </div>

<x-table-selection-bar
        :bulk-endpoint="route('spare-parts.bulk-delete')"
        :bulk-export-endpoint="route('spare-parts.bulk-export')"
        modal-name="bulk-delete-confirm-parts"
        bulkConfirmMessage="Parts linked to existing job cards or invoices will be skipped."
    >
        <x-slot name="single">
            <x-button type="button" @click="$dispatch('open-modal', 'bulk-delete-confirm')" class="flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                <x-heroicon-o-trash class="w-4 h-4" />
                Delete
            </x-button>
            <x-bulk-delete-confirm
                :name="'bulk-delete-confirm'"
                :title="'Delete this part?'"
                :confirm-label="'Delete'"
                :message="'Parts linked to existing job cards or invoices will be skipped.'"
            />
        </x-slot>
    </x-table-selection-bar>

    <x-data-table
        :empty="$spareParts->isEmpty()"
        emptyMessage="No spare parts yet. Click Add Spare Part to create one."
        emptyIcon="cube"
        :emptyHref="route('spare-parts.create')"
        emptyLabel="Add Spare Part"
        :paginator="$spareParts"
    >
        <x-slot name="header">
            <tr>
                <th class="align-middle w-10 px-6 py-3 text-left"><x-table-select-all /></th>
                <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Part Name</th>
                <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Part Code</th>
                <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Category</th>
                <th class="align-middle px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">MRP</th>
                <th class="align-middle px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Labor Charge</th>
                <th class="align-middle px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Total</th>
            </tr>
        </x-slot>

        @foreach ($spareParts as $part)
            <tr class="hover:bg-muted/50">
                <td class="align-middle px-6 py-2 whitespace-nowrap">
                    <x-table-checkbox :id="$part->id" />
                </td>
                <td class="align-middle px-6 py-2 text-sm font-medium text-gray-900">
                    {{ $part->part_name }}
                </td>
                <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-600">
                    {{ $part->part_code }}
                </td>
                <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-600">
                    {{ $part->category ?? '-' }}
                </td>
                <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-900 text-right">
                    {{ config('garage.currency_symbol') }}{{ number_format($part->mrp, 2) }}
                </td>
                <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-900 text-right">
                    {{ config('garage.currency_symbol') }}{{ number_format($part->labor_charge, 2) }}
                </td>
                <td class="align-middle px-6 py-2 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                    {{ config('garage.currency_symbol') }}{{ number_format($part->total_price, 2) }}
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-import-csv-modal name="import-csv-spare-parts" entity="spare_parts" title="Import Spare Parts" />
</div>
