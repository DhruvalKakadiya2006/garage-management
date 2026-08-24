<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-receipt-percent class="w-6 h-6 text-primary" />
                Counter Invoice {{ $counterInvoice->invoice_no }}
            </h2>
            <x-button variant="secondary" :href="route('counter-invoices.index')">
                Back
            </x-button>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            @if (session('success'))
                <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Header Info -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Invoice Details</h3>
                        <dl class="space-y-2 text-sm">
                            <div><dt class="inline font-medium">Invoice No:</dt> <dd class="inline">{{ $counterInvoice->invoice_no }}</dd></div>
                            <div><dt class="inline font-medium">Date:</dt> <dd class="inline">{{ $counterInvoice->invoice_date->format('d/m/Y') }}</dd></div>
                            <div><dt class="inline font-medium">Payment Status:</dt> <dd class="inline">
                                <x-badge :color="match ($counterInvoice->payment_status_label) {
                                    'paid' => 'green',
                                    'partially_paid' => 'amber',
                                    default => 'red',
                                }">
                                    @if ($counterInvoice->payment_status_label === 'paid')
                                        <x-heroicon-o-check-circle class="w-3.5 h-3.5" />
                                    @elseif ($counterInvoice->payment_status_label === 'partially_paid')
                                        <x-heroicon-o-clock class="w-3.5 h-3.5" />
                                    @else
                                        <x-heroicon-o-x-circle class="w-3.5 h-3.5" />
                                    @endif
                                    {{ ucwords(str_replace('_', ' ', $counterInvoice->payment_status_label)) }}
                                </x-badge>
                            </dd></div>
                            @if($counterInvoice->payment_mode)
                                <div><dt class="inline font-medium">Payment Mode:</dt> <dd class="inline capitalize">{{ str_replace('_', ' ', $counterInvoice->payment_mode) }}</dd></div>
                            @endif
                        </dl>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Customer</h3>
                        <dl class="space-y-2 text-sm">
                            @if($counterInvoice->customer_id)
                                <div><dt class="inline font-medium">Name:</dt> <dd class="inline">{{ $counterInvoice->customer->display_name }}</dd></div>
                                <div><dt class="inline font-medium">Mobile:</dt> <dd class="inline">{{ $counterInvoice->customer->mobile }}</dd></div>
                            @else
                                <div><dt class="inline font-medium">Walk-in Name:</dt> <dd class="inline">{{ $counterInvoice->walkin_name ?? '-' }}</dd></div>
                                <div><dt class="inline font-medium">Mobile:</dt> <dd class="inline">{{ $counterInvoice->walkin_mobile ?? '-' }}</dd></div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Items</h3>
                <table class="min-w-full text-xs">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="align-middle px-4 py-2 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Part</th>
                            <th class="align-middle px-4 py-2 text-center text-xs font-medium text-muted-foreground uppercase tracking-wider">Qty</th>
                            <th class="align-middle px-4 py-2 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">MRP</th>
                            <th class="align-middle px-4 py-2 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Discount %</th>
                            <th class="align-middle px-4 py-2 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($counterInvoice->items as $item)
                        <tr class="border-t hover:bg-muted/50">
                            <td class="align-middle px-4 py-2">{{ $item->sparePart->part_name }}</td>
                            <td class="align-middle px-4 py-2 text-center">{{ $item->quantity }}</td>
                            <td class="align-middle px-4 py-2 text-right">{{ config('garage.currency_symbol') }}{{ number_format($item->mrp, 2) }}</td>
                            <td class="align-middle px-4 py-2 text-right">{{ $item->discount_percent > 0 ? $item->discount_percent.'%' : '-' }}</td>
                            <td class="align-middle px-4 py-2 text-right font-medium">{{ config('garage.currency_symbol') }}{{ number_format($item->line_total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="max-w-md ml-auto space-y-2 text-sm">
                    @php
                        $subtotal = $counterInvoice->items->sum('line_total');
                        $afterDiscount = $subtotal - $counterInvoice->discount_amount;
                    @endphp
                    <div class="flex justify-between"><span>Subtotal:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($subtotal, 2) }}</span></div>
                    @if($counterInvoice->discount_amount > 0)
                    <div class="flex justify-between text-red-600">
                        <span>Invoice Discount {{ $counterInvoice->discount_percent > 0 ? '('.$counterInvoice->discount_percent.'%)' : '' }}:</span>
                        <span>-{{ config('garage.currency_symbol') }}{{ number_format($counterInvoice->discount_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between"><span>After Discount:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($afterDiscount, 2) }}</span></div>
                    @endif
                    @if($counterInvoice->gst_applicable)
                    <div class="flex justify-between"><span>CGST:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($counterInvoice->cgst_amount, 2) }}</span></div>
                    <div class="flex justify-between"><span>SGST:</span><span>{{ config('garage.currency_symbol') }}{{ number_format($counterInvoice->sgst_amount, 2) }}</span></div>
                    @endif
                    @php $roundOff = (float) $counterInvoice->round_off_amount; @endphp
                    <div class="flex justify-between"><span>Round Off:</span><span>{{ $roundOff > 0 ? '+' : '' }}{{ config('garage.currency_symbol') }}{{ number_format($roundOff, 2) }}</span></div>
                    <div class="flex justify-between text-lg font-bold border-t pt-2">
                        <span>Grand Total:</span>
                        <span>{{ config('garage.currency_symbol') }}{{ number_format($counterInvoice->grand_total, 2) }}</span>
                    </div>
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
                <div class="mb-6 p-4 bg-muted rounded-lg">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <div class="flex flex-wrap items-baseline gap-2">
                            <span class="text-gray-600">Grand Total:</span>
                            <span class="font-semibold text-lg">{{ config('garage.currency_symbol') }}{{ number_format($counterInvoice->grand_total, 2) }}</span>
                        </div>
                        <div class="flex flex-wrap items-baseline gap-2">
                            <span class="text-gray-600">Amount Paid:</span>
                            <span class="font-semibold text-lg text-green-600">{{ config('garage.currency_symbol') }}{{ number_format($counterInvoice->amount_paid, 2) }}</span>
                        </div>
                        <div class="flex flex-wrap items-baseline gap-2">
                            <span class="text-gray-600">Outstanding:</span>
                            <span class="font-semibold text-lg {{ $counterInvoice->outstanding > 0 ? 'text-red-600' : 'text-gray-600' }}">{{ config('garage.currency_symbol') }}{{ number_format($counterInvoice->outstanding, 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Payment List -->
                @if($counterInvoice->payments->count() > 0)
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900 mb-3">Payment History</h4>
                        <table class="min-w-full text-xs">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="align-middle px-4 py-2 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Date</th>
                                    <th class="align-middle px-4 py-2 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Mode</th>
                                    <th class="align-middle px-4 py-2 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Reference</th>
                                    <th class="align-middle px-4 py-2 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Amount</th>
                                    <th class="align-middle px-4 py-2 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Notes</th>
                                    <th class="align-middle px-4 py-2 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($counterInvoice->payments as $payment)
                                <tr class="border-t hover:bg-muted/50">
                                    <td class="align-middle px-4 py-2">{{ $payment->payment_date->format('d/m/Y') }}</td>
                                    <td class="align-middle px-4 py-2 capitalize">{{ str_replace('_', ' ', $payment->payment_mode) }}</td>
                                    <td class="align-middle px-4 py-2">{{ $payment->reference_no ?? '-' }}</td>
                                    <td class="align-middle px-4 py-2 text-right font-medium">{{ config('garage.currency_symbol') }}{{ number_format($payment->amount, 2) }}</td>
                                    <td class="align-middle px-4 py-2 text-gray-600">{{ $payment->notes ?? '-' }}</td>
                                    <td class="align-middle px-4 py-2 text-right text-xs whitespace-nowrap">
                                        <div class="inline-flex items-center gap-3">
                                            <a href="{{ route('payments.edit', $payment) }}" class="text-primary hover:text-primary-700">Edit</a>
                                            @can('admin')
                                                <form action="{{ route('payments.destroy', $payment) }}" method="POST"
                                                      onsubmit="return confirm('Void (delete) this payment record?');" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-800">Void</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500 mb-6">No payments recorded yet.</p>
                @endif

                <!-- Record Payment Form -->
                @if($counterInvoice->outstanding > 0)
                    <div class="border-t pt-6">
                        <h4 class="font-medium text-gray-900 mb-3">Record New Payment</h4>
                        <form action="{{ route('payments.store') }}" method="POST" class="space-y-4">
                            @csrf
                            <input type="hidden" name="payable_type" value="App\Models\CounterInvoice">
                            <input type="hidden" name="payable_id" value="{{ $counterInvoice->id }}">

                            <div class="grid grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount *</label>
                                    <input type="number" step="0.01" name="amount" max="{{ $counterInvoice->outstanding }}"
                                           value="{{ old('amount', $counterInvoice->outstanding) }}"
                                           class="block w-full rounded-md border-input shadow-sm text-sm" required>
                                    <p class="text-xs text-gray-500 mt-1">Max: {{ config('garage.currency_symbol') }}{{ number_format($counterInvoice->outstanding, 2) }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Mode *</label>
                                    <select name="payment_mode" class="block w-full rounded-md border-input shadow-sm text-sm" required>
                                        <option value="">-- Select --</option>
                                        @foreach (config('garage.option_lists.payment_mode') as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date *</label>
                                    <input type="date" name="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}"
                                           class="block w-full rounded-md border-input shadow-sm text-sm" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference No</label>
                                    <input type="text" name="reference_no" value="{{ old('reference_no') }}"
                                           class="block w-full rounded-md border-input shadow-sm text-sm"
                                           placeholder="Txn ID / Cheque No">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                <textarea name="notes" rows="2" class="block w-full rounded-md border-input shadow-sm text-sm"
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
                    <div class="inline-flex items-center gap-1.5 text-center py-2 text-green-600 font-medium">
                        <x-heroicon-o-check-circle class="w-5 h-5" />
                        Fully Paid
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
