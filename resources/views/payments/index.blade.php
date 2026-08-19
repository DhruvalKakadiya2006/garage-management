<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-banknotes class="w-6 h-6 text-primary" />
            All Payments
        </h2>
    </x-slot>

    @php
        $paymentsData = $payments->getCollection()->map(function ($p) {
            $payable = $p->payable;
            $label = $payable
                ? ($payable instanceof \App\Models\JobCard ? $payable->job_card_no : $payable->invoice_no)
                  .' · '.number_format((float) $p->amount, 2)
                : 'Orphaned payment';
            $viewUrl = $payable
                ? route($payable instanceof \App\Models\JobCard ? 'job-cards.show' : 'counter-invoices.show', $payable->id)
                : null;

            return [
                'id' => (string) $p->id,
                'label' => $label,
                'view_url' => $viewUrl,
            ];
        })->values();
    @endphp

    <div class="py-6">
        <div class="max-w-full sm:px-6 lg:px-8"
             x-data="{ ...tableSelection({ bulkEndpoint: '{{ route('payments.bulk-delete') }}' }), ...paymentList() }">

            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mb-4 bg-white rounded-xl border border-border shadow-sm px-4 py-3">
                <p class="text-sm text-gray-600">
                    <strong>Note:</strong> This is a read-only view of all payments across Job Cards and Counter Invoices, most recent first.
                    Useful for daily cash reconciliation and payment tracking.
                </p>
            </div>

            <x-table-selection-bar
                :bulk-endpoint="route('payments.bulk-delete')"
                bulk-action-label="Void"
                bulk-modal-title="Void selected payment(s)?"
                bulk-modal-confirm-label="Void"
                bulk-confirm-message="Voided payments are permanently removed; the linked document's outstanding balance updates automatically."
            >
                <x-slot name="single">
                    <span class="text-sm font-medium text-primary-800">
                        Selected:
                        <span x-text="selectedPayment ? selectedPayment.label : ''" class="font-semibold"></span>
                    </span>
                    <x-button variant="secondary" x-bind:href="base + '/' + singleId + '/edit'">
                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                        Edit
                    </x-button>
                    <x-button variant="secondary" x-cloak x-show="selectedPayment && selectedPayment.view_url"
                              x-bind:href="selectedPayment.view_url">
                        <x-heroicon-o-document-text class="w-4 h-4" />
                        View Document
                    </x-button>
                </x-slot>
            </x-table-selection-bar>

            <x-data-table
                :empty="$payments->isEmpty()"
                emptyMessage="No payments recorded yet."
                emptyIcon="banknotes"
                :paginator="$payments"
            >
                <x-slot name="header">
                    <tr>
                        <th class="w-10 px-6 py-3 text-left"><x-table-select-all /></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Payment Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Document Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Document No</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Payment Mode</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Reference</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Notes</th>
                    </tr>
                </x-slot>

                @foreach ($payments as $payment)
                    @php
                        $payable = $payment->payable;
                    @endphp
                    <tr class="hover:bg-muted/50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <x-table-checkbox :id="$payment->id" />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $payment->payment_date->format('d M Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs">
                            @if ($payable)
                                <x-badge :color="$payable instanceof \App\Models\JobCard ? 'primary' : 'green'">
                                    @if ($payable instanceof \App\Models\JobCard)
                                        <x-heroicon-o-wrench-screwdriver class="w-3.5 h-3.5" />
                                    @else
                                        <x-heroicon-o-receipt-percent class="w-3.5 h-3.5" />
                                    @endif
                                    {{ $payable instanceof \App\Models\JobCard ? 'Job Card' : 'Counter Invoice' }}
                                </x-badge>
                            @else
                                <span class="text-gray-400 italic">Deleted record</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            @if ($payable)
                                {{ $payable instanceof \App\Models\JobCard ? $payable->job_card_no : $payable->invoice_no }}
                            @else
                                <span class="text-gray-400 italic">Deleted record</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            @if ($payable)
                                @php
                                    if ($payable instanceof \App\Models\JobCard) {
                                        $customerName = $payable->customer->display_name ?? '-';
                                    } else {
                                        $customerName = $payable->customer_id
                                            ? ($payable->customer->display_name ?? '-')
                                            : ($payable->walkin_name ?? 'Walk-in');
                                    }
                                @endphp
                                {{ $customerName }}
                            @else
                                <span class="text-gray-400 italic">Deleted record</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 capitalize">
                            {{ str_replace('_', ' ', $payment->payment_mode) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $payment->reference_no ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">
                            {{ config('garage.currency_symbol') }}{{ number_format($payment->amount, 2) }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $payment->notes ? \Illuminate\Support\Str::limit($payment->notes, 30) : '-' }}
                        </td>
                    </tr>
                @endforeach
            </x-data-table>

            @if($payments->total() > 0)
                <div class="mt-6 bg-white rounded-xl border border-border shadow-sm p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Current Page Summary</h3>
                    <div class="grid grid-cols-5 gap-4 text-sm">
                        @php
                            $pageTotalAmount = $payments->sum('amount');
                            $cashPayments = $payments->where('payment_mode', 'cash')->sum('amount');
                            $upiPayments = $payments->where('payment_mode', 'upi')->sum('amount');
                            $cardPayments = $payments->where('payment_mode', 'card')->sum('amount');
                            $otherPayments = $pageTotalAmount - ($cashPayments + $upiPayments + $cardPayments);
                        @endphp
                        <div class="text-center p-4 bg-muted rounded-lg">
                            <div class="text-gray-600 mb-1">Total Amount</div>
                            <div class="text-xl font-bold text-gray-900">{{ config('garage.currency_symbol') }}{{ number_format($pageTotalAmount, 2) }}</div>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <div class="text-gray-600 mb-1">Cash</div>
                            <div class="text-xl font-bold text-green-700">{{ config('garage.currency_symbol') }}{{ number_format($cashPayments, 2) }}</div>
                        </div>
                        <div class="text-center p-4 bg-primary-50 rounded-lg">
                            <div class="text-gray-600 mb-1">UPI</div>
                            <div class="text-xl font-bold text-primary">{{ config('garage.currency_symbol') }}{{ number_format($upiPayments, 2) }}</div>
                        </div>
                        <div class="text-center p-4 bg-purple-50 rounded-lg">
                            <div class="text-gray-600 mb-1">Card</div>
                            <div class="text-xl font-bold text-purple-700">{{ config('garage.currency_symbol') }}{{ number_format($cardPayments, 2) }}</div>
                        </div>
                        <div class="text-center p-4 bg-amber-50 rounded-lg">
                            <div class="text-gray-600 mb-1">Other</div>
                            <div class="text-xl font-bold text-amber-700">{{ config('garage.currency_symbol') }}{{ number_format($otherPayments, 2) }}</div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-3 text-center">Summary shows payments on this page only.</p>
                </div>
            @endif
        </div>
    </div>

    <script>
        function paymentList() {
            const payments = @json($paymentsData);
            const base = '{{ url('payments') }}';

            return {
                base: base,
                payments: payments,

                get selectedPayment() {
                    return this.payments.find(p => p.id === this.singleId) || null;
                },
            };
        }
    </script>
</x-app-layout>
