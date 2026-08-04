<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-wrench-screwdriver class="w-6 h-6 text-primary-600" />
                Job Card {{ $jobCard->job_card_no }}
            </h2>
            <div class="space-x-2">
                <x-button :href="route('job-cards.edit', $jobCard)">
                    Edit
                </x-button>
                <x-button variant="secondary" :href="route('job-cards.index')">
                    Back
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

            <!-- Header Info -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="grid grid-cols-3 gap-6">
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Job Details</h3>
                        <dl class="space-y-2 text-sm">
                            <div><dt class="inline font-medium">JC No:</dt> <dd class="inline">{{ $jobCard->job_card_no }}</dd></div>
                            <div><dt class="inline font-medium">Date:</dt> <dd class="inline">{{ $jobCard->job_date->format('d M Y') }}</dd></div>
                            <div><dt class="inline font-medium">Type:</dt> <dd class="inline capitalize">{{ str_replace('_', ' ', $jobCard->job_type) }}</dd></div>
                            <div><dt class="inline font-medium">Status:</dt> <dd class="inline">
                                <x-badge :color="match ($jobCard->status) {
                                    'delivered' => 'green',
                                    'ready_for_delivery' => 'primary',
                                    'in_progress' => 'purple',
                                    'waiting_for_parts' => 'amber',
                                    'cancelled' => 'red',
                                    default => 'gray',
                                }">
                                    @if($jobCard->status === 'delivered')
                                        <x-heroicon-o-check-circle class="w-3.5 h-3.5 mr-1" />
                                    @elseif($jobCard->status === 'ready_for_delivery')
                                        <x-heroicon-o-check-badge class="w-3.5 h-3.5 mr-1" />
                                    @elseif($jobCard->status === 'in_progress')
                                        <x-heroicon-o-clock class="w-3.5 h-3.5 mr-1" />
                                    @elseif($jobCard->status === 'waiting_for_parts')
                                        <x-heroicon-o-pause-circle class="w-3.5 h-3.5 mr-1" />
                                    @elseif($jobCard->status === 'cancelled')
                                        <x-heroicon-o-x-circle class="w-3.5 h-3.5 mr-1" />
                                    @else
                                        <x-heroicon-o-inbox-arrow-down class="w-3.5 h-3.5 mr-1" />
                                    @endif
                                    {{ ucwords(str_replace('_', ' ', $jobCard->status)) }}
                                </x-badge>
                            </dd></div>
                            <div><dt class="inline font-medium">Payment:</dt> <dd class="inline">
                                <x-badge :color="match ($jobCard->payment_status_label) {
                                    'paid' => 'green',
                                    'partially_paid' => 'amber',
                                    default => 'red',
                                }">
                                    @if($jobCard->payment_status_label === 'paid')
                                        <x-heroicon-o-check-circle class="w-3.5 h-3.5 mr-1" />
                                    @elseif($jobCard->payment_status_label === 'partially_paid')
                                        <x-heroicon-o-clock class="w-3.5 h-3.5 mr-1" />
                                    @else
                                        <x-heroicon-o-x-circle class="w-3.5 h-3.5 mr-1" />
                                    @endif
                                    {{ ucwords(str_replace('_', ' ', $jobCard->payment_status_label)) }}
                                </x-badge>
                            </dd></div>
                        </dl>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Customer & Vehicle</h3>
                        <dl class="space-y-2 text-sm">
                            <div><dt class="inline font-medium">Customer:</dt> <dd class="inline">{{ $jobCard->customer->name }}</dd></div>
                            <div><dt class="inline font-medium">Vehicle:</dt> <dd class="inline">{{ $jobCard->vehicle->registration_no }}</dd></div>
                            <div><dt class="inline font-medium">Odometer:</dt> <dd class="inline">{{ $jobCard->odometer_reading_km ?? '-' }} km</dd></div>
                        </dl>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Assignment</h3>
                        <dl class="space-y-2 text-sm">
                            <div><dt class="inline font-medium">Technician:</dt> <dd class="inline">{{ $jobCard->assignedTechnician->name ?? '-' }}</dd></div>
                            <div><dt class="inline font-medium">Supervisor:</dt> <dd class="inline">{{ $jobCard->supervisor->name ?? '-' }}</dd></div>
                            <div><dt class="inline font-medium">Delivery:</dt> <dd class="inline">{{ $jobCard->promised_delivery_date?->format('d M Y') ?? '-' }}</dd></div>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Complaints -->
            @if($jobCard->complaints->count() > 0)
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Complaints</h3>
                @foreach($jobCard->complaints as $complaint)
                    <div class="mb-2 text-sm">{{ $complaint->description }} — {{ config('garage.currency_symbol') }}{{ number_format($complaint->charge, 2) }}</div>
                @endforeach
            </div>
            @endif

            <!-- Parts -->
            @if($jobCard->parts->count() > 0)
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Parts Used</h3>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">Part</th>
                            <th class="px-4 py-2 text-center">Qty</th>
                            <th class="px-4 py-2 text-right">MRP</th>
                            <th class="px-4 py-2 text-right">Labor</th>
                            <th class="px-4 py-2 text-center">Warranty</th>
                            <th class="px-4 py-2 text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($jobCard->parts as $part)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $part->sparePart->part_name }}</td>
                            <td class="px-4 py-2 text-center">{{ $part->quantity }}</td>
                            <td class="px-4 py-2 text-right">{{ config('garage.currency_symbol') }}{{ number_format($part->mrp, 2) }}</td>
                            <td class="px-4 py-2 text-right">{{ config('garage.currency_symbol') }}{{ number_format($part->labor_charge, 2) }}</td>
                            <td class="px-4 py-2 text-center">{{ $part->under_warranty ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ config('garage.currency_symbol') }}{{ number_format($part->line_total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            <!-- Labours -->
            @if($jobCard->labours->count() > 0)
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Labour</h3>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">Service</th>
                            <th class="px-4 py-2 text-left">Technician</th>
                            <th class="px-4 py-2 text-center">Qty</th>
                            <th class="px-4 py-2 text-right">Rate</th>
                            <th class="px-4 py-2 text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($jobCard->labours as $labour)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $labour->labourMaster->service_name }}</td>
                            <td class="px-4 py-2">{{ $labour->technician->name ?? '-' }}</td>
                            <td class="px-4 py-2 text-center">{{ $labour->qty }}</td>
                            <td class="px-4 py-2 text-right">{{ config('garage.currency_symbol') }}{{ number_format($labour->rate, 2) }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ config('garage.currency_symbol') }}{{ number_format($labour->line_total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            <!-- Totals -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="max-w-md ml-auto space-y-2 text-sm">
                    @php
                        $complaintsTotal = $jobCard->complaints->sum('charge');
                        $partsTotal = $jobCard->parts->sum('line_total');
                        $laboursTotal = $jobCard->labours->sum('line_total');
                        $subtotal = $complaintsTotal + $partsTotal + $laboursTotal;
                        $afterDiscount = $subtotal - $jobCard->discount_amount;
                    @endphp
                    <div class="flex justify-between"><span>Complaints Total:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($complaintsTotal, 2) }}</span></div>
                    <div class="flex justify-between"><span>Parts Total:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($partsTotal, 2) }}</span></div>
                    <div class="flex justify-between"><span>Labour Total:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($laboursTotal, 2) }}</span></div>
                    <div class="flex justify-between font-medium"><span>Subtotal:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($subtotal, 2) }}</span></div>
                    @if($jobCard->discount_amount > 0)
                    <div class="flex justify-between text-red-600"><span>Discount:</span><span>-{{ config('garage.currency_symbol') }}{{ number_format($jobCard->discount_amount, 2) }}</span></div>
                    @endif
                    @if($jobCard->gst_applicable)
                    <div class="flex justify-between"><span>CGST:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($jobCard->cgst_amount, 2) }}</span></div>
                    <div class="flex justify-between"><span>SGST:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($jobCard->sgst_amount, 2) }}</span></div>
                    @endif
                    @php $roundOff = (float) $jobCard->round_off_amount; @endphp
                    <div class="flex justify-between"><span>Round Off:</span><span>{{ $roundOff > 0 ? '+' : '' }}{{ config('garage.currency_symbol') }}{{ number_format($roundOff, 2) }}</span></div>
                    <div class="flex justify-between text-lg font-bold border-t pt-2"><span>Grand Total:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($jobCard->grand_total, 2) }}</span></div>
                </div>
            </div>

            <!-- Payments Section -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Payment Records</h3>
                
                @if($errors->any())
                    <div class="mb-4 bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-md">
                        <ul class="list-disc list-inside text-sm">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Payment Summary -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600">Grand Total:</span>
                            <span class="font-semibold text-lg ml-2">{{ config('garage.currency_symbol') }}{{ number_format($jobCard->grand_total, 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Amount Paid:</span>
                            <span class="font-semibold text-lg ml-2 text-green-600">{{ config('garage.currency_symbol') }}{{ number_format($jobCard->amount_paid, 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Outstanding:</span>
                            <span class="font-semibold text-lg ml-2 {{ $jobCard->outstanding > 0 ? 'text-red-600' : 'text-gray-600' }}">{{ config('garage.currency_symbol') }}{{ number_format($jobCard->outstanding, 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Payment List -->
                @if($jobCard->payments->count() > 0)
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900 mb-3">Payment History</h4>
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left">Date</th>
                                    <th class="px-4 py-2 text-left">Mode</th>
                                    <th class="px-4 py-2 text-left">Reference</th>
                                    <th class="px-4 py-2 text-right">Amount</th>
                                    <th class="px-4 py-2 text-left">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($jobCard->payments as $payment)
                                <tr class="border-t">
                                    <td class="px-4 py-2">{{ $payment->payment_date->format('d M Y') }}</td>
                                    <td class="px-4 py-2 capitalize">{{ str_replace('_', ' ', $payment->payment_mode) }}</td>
                                    <td class="px-4 py-2">{{ $payment->reference_no ?? '-' }}</td>
                                    <td class="px-4 py-2 text-right font-medium">{{ config('garage.currency_symbol') }}{{ number_format($payment->amount, 2) }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ $payment->notes ?? '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500 mb-6">No payments recorded yet.</p>
                @endif

                <!-- Record Payment Form -->
                @if($jobCard->outstanding > 0)
                    <div class="border-t pt-6">
                        <h4 class="font-medium text-gray-900 mb-3">Record New Payment</h4>
                        <form action="{{ route('payments.store') }}" method="POST" class="space-y-4">
                            @csrf
                            <input type="hidden" name="payable_type" value="App\Models\JobCard">
                            <input type="hidden" name="payable_id" value="{{ $jobCard->id }}">

                            <div class="grid grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount *</label>
                                    <input type="number" step="0.01" name="amount" max="{{ $jobCard->outstanding }}"
                                           value="{{ old('amount', $jobCard->outstanding) }}"
                                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm" required>
                                    <p class="text-xs text-gray-500 mt-1">Max: {{ config('garage.currency_symbol') }}{{ number_format($jobCard->outstanding, 2) }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Mode *</label>
                                    <select name="payment_mode" class="block w-full rounded-md border-gray-300 shadow-sm text-sm" required>
                                        <option value="">-- Select --</option>
                                        @foreach (config('garage.option_lists.payment_mode') as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date *</label>
                                    <input type="date" name="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}"
                                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference No</label>
                                    <input type="text" name="reference_no" value="{{ old('reference_no') }}"
                                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm"
                                           placeholder="Txn ID / Cheque No">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                <textarea name="notes" rows="2" class="block w-full rounded-md border-gray-300 shadow-sm text-sm"
                                          placeholder="Optional payment notes">{{ old('notes') }}</textarea>
                            </div>

                            <div class="flex justify-end">
                                <x-button variant="success" type="submit">
                                    Record Payment
                                </x-button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="inline-flex items-center text-center py-4 text-green-600 font-medium">
                        <x-heroicon-o-check-circle class="w-5 h-5 mr-1.5" />
                        Fully Paid
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
