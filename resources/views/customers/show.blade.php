<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-users class="w-6 h-6 text-primary-600" />
                {{ $customer->display_name }}
            </h2>
            <div class="flex items-center gap-2">
                <x-button variant="secondary" :href="route('customers.edit', $customer)">
                    <x-heroicon-o-pencil-square class="w-4 h-4 mr-1.5" />
                    Edit
                </x-button>
                <x-button variant="secondary" :href="route('customers.index')">
                    Back to Customers
                </x-button>
            </div>
        </div>
    </x-slot>

    @php
        $outstanding = $customer->jobCards->sum(fn ($jc) => (float) $jc->outstanding)
            + $customer->counterInvoices->sum(fn ($ci) => (float) $ci->outstanding);
        $totalVisits = $customer->job_cards_count + $customer->counter_invoices_count;
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Summary --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <x-card class="!p-5">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Mobile</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ $customer->mobile }}</div>
                </x-card>
                <x-card class="!p-5">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Customer Type</div>
                    <div class="mt-1.5">
                        <x-badge :color="match ($customer->customer_type) {
                            'regular' => 'green',
                            'counter' => 'amber',
                            default => 'gray',
                        }">
                            {{ ucwords(str_replace('_', ' ', $customer->customer_type)) }}
                        </x-badge>
                    </div>
                </x-card>
                <x-card class="!p-5">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Outstanding</div>
                    <div class="mt-1 text-lg font-semibold {{ $outstanding > 0 ? 'text-red-600' : 'text-gray-900' }}">
                        {{ config('garage.currency_symbol') }}{{ number_format($outstanding, 2) }}
                    </div>
                </x-card>
                <x-card class="!p-5">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Visits</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ $totalVisits }}</div>
                </x-card>
            </div>

            {{-- Contact details --}}
            <x-card>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Alt. Mobile</div>
                        <div class="mt-1 text-gray-800">{{ $customer->alt_mobile ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Email</div>
                        <div class="mt-1 text-gray-800">{{ $customer->email ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">City</div>
                        <div class="mt-1 text-gray-800">{{ $customer->city ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">GSTIN</div>
                        <div class="mt-1 text-gray-800">{{ $customer->gstin ?? '—' }}</div>
                    </div>
                    <div class="col-span-2 lg:col-span-4">
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Address</div>
                        <div class="mt-1 text-gray-800">{{ $customer->address ?? '—' }}</div>
                    </div>
                </div>
            </x-card>

            {{-- Vehicles --}}
            <x-card>
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-semibold text-gray-900">Vehicles</h3>
                    <x-button variant="secondary" :href="route('vehicles.create', ['customer_id' => $customer->id])">
                        <x-heroicon-o-plus class="w-4 h-4 mr-1.5" />
                        Add Vehicle
                    </x-button>
                </div>
                @if ($customer->vehicles->isEmpty())
                    <p class="text-sm text-gray-500">No vehicles registered for this customer.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registration No.</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Battery</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($customer->vehicles as $vehicle)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium">{{ $vehicle->registration_no ?? '—' }}</span>
                                                @if ($vehicle->is_non_rto)
                                                    <x-badge color="amber">NON-RTO</x-badge>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                            {{ $vehicle->vehicle_brand }} {{ $vehicle->model }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                            {{ $vehicle->battery_type ?? '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            {{-- Job Cards --}}
            <x-card>
                <h3 class="font-semibold text-gray-900 mb-3">Job Cards</h3>
                @if ($customer->jobCards->isEmpty())
                    <p class="text-sm text-gray-500">No job cards yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Job Card No.</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Grand Total</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Outstanding</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($customer->jobCards as $jobCard)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-primary-600 font-medium">
                                            {{ $jobCard->job_card_no }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                            {{ $jobCard->job_date->format('d M Y') }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                            {{ ucwords(str_replace('_', ' ', $jobCard->status)) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 text-right">
                                            {{ config('garage.currency_symbol') }}{{ number_format($jobCard->grand_total, 2) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right {{ $jobCard->outstanding > 0 ? 'font-medium text-red-600' : 'text-gray-600' }}">
                                            {{ config('garage.currency_symbol') }}{{ number_format($jobCard->outstanding, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            {{-- Counter Invoices --}}
            <x-card>
                <h3 class="font-semibold text-gray-900 mb-3">Counter Invoices</h3>
                @if ($customer->counterInvoices->isEmpty())
                    <p class="text-sm text-gray-500">No counter invoices yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice No.</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Grand Total</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Outstanding</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($customer->counterInvoices as $invoice)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-primary-600 font-medium">
                                            {{ $invoice->invoice_no }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                            {{ $invoice->invoice_date->format('d M Y') }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 text-right">
                                            {{ config('garage.currency_symbol') }}{{ number_format($invoice->grand_total, 2) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right {{ $invoice->outstanding > 0 ? 'font-medium text-red-600' : 'text-gray-600' }}">
                                            {{ config('garage.currency_symbol') }}{{ number_format($invoice->outstanding, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

        </div>
    </div>
</x-app-layout>
