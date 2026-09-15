<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-receipt-percent class="w-6 h-6 text-primary" />
                Counter Invoice {{ $counterInvoice->invoice_no }}
            </h2>
            <div class="flex gap-2">
                <x-button variant="secondary" :href="route('counter-invoices.pdf', $counterInvoice)">
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                    PDF
                </x-button>
                <a href="{{ route('counter-invoices.share-whatsapp', $counterInvoice) }}" class="inline-flex items-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-600">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    WhatsApp
                </a>
                <x-button variant="secondary" :href="route('counter-invoices.index')">
                    Back
                </x-button>
            </div>
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
                                           class="block w-full rounded-md border border-gray-300 bg-white shadow-sm text-sm" required>
                                    <p class="text-xs text-gray-500 mt-1">Max: {{ config('garage.currency_symbol') }}{{ number_format($counterInvoice->outstanding, 2) }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Mode *</label>
                                    <select name="payment_mode" class="block w-full rounded-md border border-gray-300 bg-white shadow-sm text-sm" required>
                                        <option value="">-- Select --</option>
                                        @foreach (config('garage.option_lists.payment_mode') as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date *</label>
                                    <input type="date" name="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}"
                                           class="block w-full rounded-md border border-gray-300 bg-white shadow-sm text-sm" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference No</label>
                                    <input type="text" name="reference_no" value="{{ old('reference_no') }}"
                                           class="block w-full rounded-md border border-gray-300 bg-white shadow-sm text-sm"
                                           placeholder="Txn ID / Cheque No">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                <textarea name="notes" rows="2" class="block w-full rounded-md border border-gray-300 bg-white shadow-sm text-sm"
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
