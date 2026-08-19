<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-receipt-percent class="w-6 h-6 text-primary" />
                Counter Invoices
            </h2>
            <x-button :href="route('counter-invoices.create')">
                <x-heroicon-o-plus class="w-4 h-4" />
                New Counter Invoice
            </x-button>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            <x-data-table
                :empty="$invoices->isEmpty()"
                emptyMessage='No counter invoices yet. Click "New Counter Invoice" to create one.'
                emptyIcon="receipt-percent"
                :emptyHref="route('counter-invoices.create')"
                emptyLabel="New Counter Invoice"
                :paginator="$invoices"
            >
                <x-slot name="header">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Invoice No.</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Payment</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Grand Total</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Actions</th>
                    </tr>
                </x-slot>

                @foreach ($invoices as $invoice)
                    <tr class="hover:bg-muted/50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $invoice->invoice_no }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $invoice->invoice_date->format('d M Y') }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            @if($invoice->customer_id)
                                {{ $invoice->customer->display_name }}
                            @else
                                {{ $invoice->walkin_name ?? 'Walk-in' }}
                                @if($invoice->walkin_mobile)
                                    <br><span class="text-xs text-gray-500">{{ $invoice->walkin_mobile }}</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs">
                            <x-badge :color="match ($invoice->payment_status_label) {
                                'paid' => 'green',
                                'partially_paid' => 'amber',
                                default => 'red',
                            }">
                                @if ($invoice->payment_status_label === 'paid')
                                    <x-heroicon-o-check-circle class="w-3.5 h-3.5" />
                                @elseif ($invoice->payment_status_label === 'partially_paid')
                                    <x-heroicon-o-clock class="w-3.5 h-3.5" />
                                @else
                                    <x-heroicon-o-x-circle class="w-3.5 h-3.5" />
                                @endif
                                {{ ucwords(str_replace('_', ' ', $invoice->payment_status_label)) }}
                            </x-badge>
                            @if($invoice->payment_mode)
                                <div class="text-xs text-gray-500 mt-1">{{ ucfirst(str_replace('_', ' ', $invoice->payment_mode)) }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">
                            {{ config('garage.currency_symbol') }}{{ number_format($invoice->grand_total, 2) }}
                        </td>
                        <x-data-table-actions
                            :model="$invoice"
                            :viewRoute="fn ($i) => route('counter-invoices.show', $i)"
                            :editRoute="fn ($i) => route('counter-invoices.edit', $i)"
                            :deleteRoute="fn ($i) => route('counter-invoices.destroy', $i)"
                            confirm="Delete this invoice?"
                        />
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</x-app-layout>
