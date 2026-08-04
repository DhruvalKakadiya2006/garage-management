<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-document-text class="w-6 h-6 text-primary-600" />
                Estimates
            </h2>
            <x-button :href="route('estimates.create')">
                <x-heroicon-o-plus class="w-4 h-4 mr-1.5" />
                New Estimate
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
                :empty="$estimates->isEmpty()"
                emptyMessage='No estimates yet. Click "New Estimate" to create one.'
                emptyIcon="document-text"
                :emptyHref="route('estimates.create')"
                emptyLabel="New Estimate"
                :paginator="$estimates"
            >
                <x-slot name="header">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estimate No.</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Grand Total</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </x-slot>

                @foreach ($estimates as $estimate)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $estimate->estimate_no }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $estimate->estimate_date->format('d M Y') }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $estimate->customer->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $estimate->vehicle->registration_no ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <x-badge :color="match ($estimate->status) {
                                'approved' => 'green',
                                'sent' => 'primary',
                                'rejected' => 'red',
                                'expired' => 'gray',
                                default => 'amber',
                            }">
                                @if($estimate->status === 'approved')
                                    <x-heroicon-o-check-circle class="w-3.5 h-3.5 mr-1" />
                                @elseif($estimate->status === 'sent')
                                    <x-heroicon-o-check-badge class="w-3.5 h-3.5 mr-1" />
                                @elseif($estimate->status === 'rejected')
                                    <x-heroicon-o-x-circle class="w-3.5 h-3.5 mr-1" />
                                @elseif($estimate->status === 'expired')
                                    <x-heroicon-o-archive-box class="w-3.5 h-3.5 mr-1" />
                                @else
                                    <x-heroicon-o-clock class="w-3.5 h-3.5 mr-1" />
                                @endif
                                {{ ucfirst($estimate->status) }}
                            </x-badge>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">
                            {{ config('garage.currency_symbol') }}{{ number_format($estimate->grand_total, 2) }}
                        </td>
                        <x-data-table-actions
                            :model="$estimate"
                            :viewRoute="fn ($e) => route('estimates.show', $e)"
                            :editRoute="fn ($e) => route('estimates.edit', $e)"
                            :deleteRoute="fn ($e) => route('estimates.destroy', $e)"
                            confirm="Delete this estimate?"
                        />
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</x-app-layout>
