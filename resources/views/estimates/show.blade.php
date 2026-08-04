<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-document-text class="w-6 h-6 text-primary-600" />
                Estimate {{ $estimate->estimate_no }}
            </h2>
            <div class="space-x-2">
                <x-button :href="route('estimates.edit', $estimate)">
                    Edit
                </x-button>
                <x-button variant="secondary" :href="route('estimates.index')">
                    Back to List
                </x-button>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            @if (session('success'))
                <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-md">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Header Info -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Estimate Details</h3>
                        <dl class="space-y-2 text-sm">
                            <div><dt class="inline font-medium">Estimate No:</dt> <dd class="inline">{{ $estimate->estimate_no }}</dd></div>
                            <div><dt class="inline font-medium">Date:</dt> <dd class="inline">{{ $estimate->estimate_date->format('d M Y') }}</dd></div>
                            <div><dt class="inline font-medium">Valid Until:</dt> <dd class="inline">{{ $estimate->valid_until ? $estimate->valid_until->format('d M Y') : '-' }}</dd></div>
                            <div><dt class="inline font-medium">Status:</dt> <dd class="inline">
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
                            </dd></div>
                        </dl>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Customer & Vehicle</h3>
                        <dl class="space-y-2 text-sm">
                            <div><dt class="inline font-medium">Customer:</dt> <dd class="inline">{{ $estimate->customer->name }}</dd></div>
                            <div><dt class="inline font-medium">Mobile:</dt> <dd class="inline">{{ $estimate->customer->mobile }}</dd></div>
                            <div><dt class="inline font-medium">Vehicle:</dt> <dd class="inline">{{ $estimate->vehicle->registration_no }}</dd></div>
                            <div><dt class="inline font-medium">Brand/Model:</dt> <dd class="inline">{{ $estimate->vehicle->vehicle_brand }} {{ $estimate->vehicle->model }}</dd></div>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Parts -->
            @if($estimate->parts->count() > 0)
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Parts</h3>
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Part</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">Qty</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">MRP</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Labor</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($estimate->parts as $part)
                        <tr>
                            <td class="px-4 py-2 text-sm">{{ $part->sparePart->part_name }}</td>
                            <td class="px-4 py-2 text-sm text-center">{{ $part->quantity }}</td>
                            <td class="px-4 py-2 text-sm text-right">{{ config('garage.currency_symbol') }}{{ number_format($part->mrp, 2) }}</td>
                            <td class="px-4 py-2 text-sm text-right">{{ config('garage.currency_symbol') }}{{ number_format($part->labor_charge, 2) }}</td>
                            <td class="px-4 py-2 text-sm text-right font-medium">{{ config('garage.currency_symbol') }}{{ number_format($part->line_total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            <!-- Labours -->
            @if($estimate->labours->count() > 0)
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Labour</h3>
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Service</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">Qty</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Rate</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($estimate->labours as $labour)
                        <tr>
                            <td class="px-4 py-2 text-sm">{{ $labour->labourMaster->service_name }}</td>
                            <td class="px-4 py-2 text-sm text-center">{{ $labour->qty }}</td>
                            <td class="px-4 py-2 text-sm text-right">{{ config('garage.currency_symbol') }}{{ number_format($labour->rate, 2) }}</td>
                            <td class="px-4 py-2 text-sm text-right font-medium">{{ config('garage.currency_symbol') }}{{ number_format($labour->line_total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            <!-- Totals -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="max-w-md ml-auto space-y-2">
                    @php
                        $partsTotal = $estimate->parts->sum('line_total');
                        $laboursTotal = $estimate->labours->sum('line_total');
                        $subtotal = $partsTotal + $laboursTotal;
                        $afterDiscount = $subtotal - $estimate->discount_amount;
                    @endphp
                    <div class="flex justify-between text-sm"><span>Parts Subtotal:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($partsTotal, 2) }}</span></div>
                    <div class="flex justify-between text-sm"><span>Labour Subtotal:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($laboursTotal, 2) }}</span></div>
                    <div class="flex justify-between text-sm font-medium"><span>Subtotal:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($subtotal, 2) }}</span></div>
                    @if($estimate->discount_amount > 0)
                    <div class="flex justify-between text-sm text-red-600"><span>Discount {{ $estimate->discount_percent > 0 ? '('.$estimate->discount_percent.'%)' : '' }}:</span><span>-{{ config('garage.currency_symbol') }}{{ number_format($estimate->discount_amount, 2) }}</span></div>
                    <div class="flex justify-between text-sm"><span>After Discount:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($afterDiscount, 2) }}</span></div>
                    @endif
                    @if($estimate->gst_applicable)
                    <div class="flex justify-between text-sm"><span>CGST:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($estimate->cgst_amount, 2) }}</span></div>
                    <div class="flex justify-between text-sm"><span>SGST:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($estimate->sgst_amount, 2) }}</span></div>
                    @endif
                    @php $roundOff = (float) $estimate->round_off_amount; @endphp
                    <div class="flex justify-between text-sm"><span>Round Off:</span><span>{{ $roundOff > 0 ? '+' : '' }}{{ config('garage.currency_symbol') }}{{ number_format($roundOff, 2) }}</span></div>
                    <div class="flex justify-between text-lg font-bold border-t pt-2"><span>Grand Total:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($estimate->grand_total, 2) }}</span></div>
                </div>
            </div>

            @if($estimate->notes)
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-2">Notes</h3>
                <p class="text-sm text-gray-600 whitespace-pre-line">{{ $estimate->notes }}</p>
            </div>
            @endif

            @if($estimate->job_card_id && $estimate->jobCard)
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center gap-3">
                    <x-badge color="green">
                        <x-heroicon-o-check-circle class="w-3.5 h-3.5 mr-1" />
                        Converted to Job Card
                    </x-badge>
                    <a href="{{ route('job-cards.show', $estimate->jobCard) }}"
                       class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700">
                        {{ $estimate->jobCard->job_card_no }}
                        <x-heroicon-o-arrow-right class="w-4 h-4" />
                    </a>
                </div>
            </div>
            @elseif($estimate->status === 'approved')
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Convert to Job Card</h3>
                <p class="text-sm text-gray-600 mb-4">This approved estimate can be converted into a Job Card. Parts stock will be deducted.</p>
                <form method="POST" action="{{ route('estimates.convert-to-job-card', $estimate) }}"
                      onsubmit="return confirm('Convert this estimate into a Job Card? Parts stock will be deducted.');">
                    @csrf
                    <x-button type="submit" variant="success">
                        <x-heroicon-o-arrow-right-circle class="w-4 h-4 mr-1" />
                        Convert to Job Card
                    </x-button>
                </form>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>
