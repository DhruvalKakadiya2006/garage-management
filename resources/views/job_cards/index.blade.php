<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-wrench-screwdriver class="w-6 h-6 text-primary-600" />
                Job Cards
            </h2>
            <x-button :href="route('job-cards.create')">
                <x-heroicon-o-plus class="w-4 h-4 mr-1.5" />
                New Job Card
            </x-button>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Filters -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <form method="GET" action="{{ route('job-cards.index') }}" class="flex gap-4 items-end">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Technician</label>
                        <select name="technician" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">-- All Technicians --</option>
                            @foreach ($technicians as $tech)
                                <option value="{{ $tech->id }}" {{ request('technician') == $tech->id ? 'selected' : '' }}>
                                    {{ $tech->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">-- All Statuses --</option>
                            @foreach (config('garage.option_lists.job_card_status') as $statusValue => $statusLabel)
                                <option value="{{ $statusValue }}" {{ request('status') == $statusValue ? 'selected' : '' }}>
                                    {{ $statusLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm rounded-md">Filter</button>
                        <a href="{{ route('job-cards.index') }}" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm rounded-md">Clear</a>
                    </div>
                </form>
            </div>

            <x-data-table
                :empty="$jobCards->isEmpty()"
                emptyMessage='No job cards yet. Click "New Job Card" to create one.'
                emptyIcon="wrench-screwdriver"
                :emptyHref="route('job-cards.create')"
                emptyLabel="New Job Card"
                :paginator="$jobCards"
            >
                <x-slot name="header">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">JC No.</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Technician</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Grand Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </x-slot>

                @foreach ($jobCards as $jc)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $jc->job_card_no }}
                            @if($jc->urgent)
                                <span class="ml-1 inline-flex text-red-600" title="Urgent">
                                    <x-heroicon-o-fire class="w-4 h-4" />
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $jc->job_date->format('d M Y') }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $jc->customer->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $jc->vehicle->registration_no ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $jc->assignedTechnician->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <x-badge :color="match ($jc->status) {
                                'delivered' => 'green',
                                'ready_for_delivery' => 'primary',
                                'in_progress' => 'purple',
                                'waiting_for_parts' => 'amber',
                                'cancelled' => 'red',
                                default => 'gray',
                            }">
                                @if($jc->status === 'delivered')
                                    <x-heroicon-o-check-circle class="w-3.5 h-3.5 mr-1" />
                                @elseif($jc->status === 'ready_for_delivery')
                                    <x-heroicon-o-check-badge class="w-3.5 h-3.5 mr-1" />
                                @elseif($jc->status === 'in_progress')
                                    <x-heroicon-o-clock class="w-3.5 h-3.5 mr-1" />
                                @elseif($jc->status === 'waiting_for_parts')
                                    <x-heroicon-o-pause-circle class="w-3.5 h-3.5 mr-1" />
                                @elseif($jc->status === 'cancelled')
                                    <x-heroicon-o-x-circle class="w-3.5 h-3.5 mr-1" />
                                @else
                                    <x-heroicon-o-inbox-arrow-down class="w-3.5 h-3.5 mr-1" />
                                @endif
                                {{ ucwords(str_replace('_', ' ', $jc->status)) }}
                            </x-badge>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">
                            {{ config('garage.currency_symbol') }}{{ number_format($jc->grand_total, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <x-badge :color="match ($jc->payment_status_label) {
                                'paid' => 'green',
                                'partially_paid' => 'amber',
                                default => 'red',
                            }">
                                @if($jc->payment_status_label === 'paid')
                                    <x-heroicon-o-check-circle class="w-3.5 h-3.5 mr-1" />
                                @elseif($jc->payment_status_label === 'partially_paid')
                                    <x-heroicon-o-clock class="w-3.5 h-3.5 mr-1" />
                                @else
                                    <x-heroicon-o-x-circle class="w-3.5 h-3.5 mr-1" />
                                @endif
                                {{ ucwords(str_replace('_', ' ', $jc->payment_status_label)) }}
                            </x-badge>
                        </td>
                        <x-data-table-actions
                            :model="$jc"
                            :viewRoute="fn ($j) => route('job-cards.show', $j)"
                            :editRoute="fn ($j) => route('job-cards.edit', $j)"
                        />
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</x-app-layout>
