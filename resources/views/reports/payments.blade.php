<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-credit-card class="w-6 h-6 text-primary" />
                Payments Report
            </h2>
            <a href="{{ route('reports.index') }}" 
               class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-800">
                <x-heroicon-o-arrow-left class="w-4 h-4" />
                Back to Reports
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Date Range Filter -->
            <x-date-range-filter 
                :fromDate="$fromDate" 
                :toDate="$toDate" 
                :action="route('reports.payments')" 
            />

            <!-- Total Collected -->
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 shadow-lg rounded-xl p-8 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm uppercase tracking-wide mb-2">Total Payments Collected</p>
                        <p class="text-4xl font-bold">{{ config('garage.currency_symbol') }}{{ number_format($totalPayments, 2) }}</p>
                        <p class="text-purple-100 text-sm mt-2">
                            {{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }} 
                            to 
                            {{ \Carbon\Carbon::parse($toDate)->format('d M Y') }}
                        </p>
                    </div>
                    <div class="opacity-50">
                        <x-heroicon-o-banknotes class="w-16 h-16" />
                    </div>
                </div>
            </div>

            <!-- Breakdown by Payment Mode -->
            <div class="bg-white shadow-sm rounded-xl p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Breakdown by Payment Mode</h3>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                    @php
                        $modeLabels = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank_transfer' => 'Bank Transfer', 'cheque' => 'Cheque'];
                    @endphp
                    @foreach($paymentsByMode as $mode => $summary)
                        <div class="text-center p-4 bg-muted rounded-lg">
                            <div class="text-gray-600 mb-1">{{ $modeLabels[$mode] }}</div>
                            <div class="text-xl font-bold text-gray-900">{{ config('garage.currency_symbol') }}{{ number_format($summary['total'], 2) }}</div>
                            <div class="text-xs text-gray-500 mt-1">{{ $summary['count'] }} payment(s)</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Individual Payments -->
            <div class="bg-white shadow-sm rounded-xl p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Payments Received</h3>
                @if($payments->isEmpty())
                    <p class="text-sm text-gray-500">No payments recorded in this date range.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Payment Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Document</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Payment Mode</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Reference</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($payments as $payment)
                                    @php
                                        $payable = $payment->payable;
                                        if ($payable) {
                                            if ($payable instanceof \App\Models\JobCard) {
                                                $docNo = $payable->job_card_no;
                                                $customerName = $payable->customer->display_name ?? '-';
                                                $docRoute = route('job-cards.show', $payable->id);
                                            } else {
                                                $docNo = $payable->invoice_no;
                                                $customerName = $payable->customer_id
                                                    ? ($payable->customer->display_name ?? '-')
                                                    : ($payable->walkin_name ?? 'Walk-in');
                                                $docRoute = route('counter-invoices.show', $payable->id);
                                            }
                                        }
                                    @endphp
                                    <tr class="hover:bg-muted/50">
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-900">
                                            {{ $payment->payment_date->format('d M Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if ($payable)
                                                <a href="{{ $docRoute }}" class="font-medium text-primary hover:text-primary-700">
                                                    {{ $docNo }}
                                                </a>
                                            @else
                                                <span class="text-gray-400 italic">Deleted record</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-gray-600">
                                            {{ $payable ? $customerName : '—' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600 capitalize">
                                            {{ str_replace('_', ' ', $payment->payment_mode) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                            {{ $payment->reference_no ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-gray-900">
                                            {{ config('garage.currency_symbol') }}{{ number_format($payment->amount, 2) }}
                                        </td>
                                        <td class="px-6 py-4 text-gray-500">
                                            {{ $payment->notes ? \Illuminate\Support\Str::limit($payment->notes, 30) : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
