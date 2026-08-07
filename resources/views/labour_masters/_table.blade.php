<div class="mb-4 flex items-center justify-end">
    <x-button :href="route('labour-masters.create')">
        <x-heroicon-o-plus class="w-4 h-4 mr-1.5" />
        Add Labour Service
    </x-button>
</div>

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
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service Name</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Standard Rate</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
        </tr>
    </x-slot>

    @foreach ($labourMasters as $labour)
        <tr>
            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                {{ $labour->service_name }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <x-badge :color="match ($labour->category) {
                    'battery' => 'green',
                    'motor' => 'blue',
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
            <x-data-table-actions
                :model="$labour"
                :editRoute="fn ($l) => route('labour-masters.edit', $l)"
                :deleteRoute="fn ($l) => route('labour-masters.destroy', $l)"
                confirm="Delete this labour service?"
            />
        </tr>
    @endforeach
</x-data-table>
