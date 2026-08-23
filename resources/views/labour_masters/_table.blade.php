<div x-data="tableSelection({ bulkEndpoint: '{{ route('labour-masters.bulk-delete') }}', bulkExportEndpoint: '{{ route('labour-masters.bulk-export') }}', modalName: 'bulk-delete-confirm-labour' })">

    <div class="mb-4 flex items-center justify-end">
        <x-button :href="route('labour-masters.create')">
            <x-heroicon-o-plus class="w-4 h-4" />
            Add Labour Service
        </x-button>
    </div>

    <x-table-selection-bar
        :bulk-endpoint="route('labour-masters.bulk-delete')"
        :bulk-export-endpoint="route('labour-masters.bulk-export')"
        modal-name="bulk-delete-confirm-labour"
        bulkConfirmMessage="Labour services linked to existing job cards will be skipped."
    >
        <x-slot name="single">
            <x-button variant="secondary" type="button"
                      @click="window.location.href = '{{ url('labour-masters') }}' + '/' + singleId + '/edit'">
                <x-heroicon-o-pencil-square class="w-4 h-4" />
                Edit
            </x-button>
        </x-slot>
    </x-table-selection-bar>

    <x-data-table
        :empty="$labourMasters->isEmpty()"
        emptyMessage='No labour services yet. Click "Add Labour Service" to create one.'
        emptyIcon="clipboard-document-list"
        :emptyHref="route('labour-masters.create')"
        emptyLabel="Add Labour Service"
        :paginator="$labourMasters"
    >
        <x-slot name="header">
            <tr>
                <th class="w-10 px-6 py-3 text-left"><x-table-select-all /></th>
                <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Service Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Category</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Standard Rate</th>
            </tr>
        </x-slot>

        @foreach ($labourMasters as $labour)
            <tr class="hover:bg-muted/50">
                <td class="px-6 py-4 whitespace-nowrap">
                    <x-table-checkbox :id="$labour->id" />
                </td>
                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                    {{ $labour->service_name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <x-badge :color="match ($labour->category) {
                        'battery' => 'green',
                        'motor' => 'primary',
                        'brakes' => 'red',
                        'electrical' => 'purple',
                        default => 'gray',
                    }">
                        {{ ucwords($labour->category) }}
                    </x-badge>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">
                    {{ config('garage.currency_symbol') }}{{ number_format($labour->standard_rate, 2) }}
                </td>
            </tr>
        @endforeach
    </x-data-table>
</div>