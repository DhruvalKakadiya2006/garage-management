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

    @php
        $invoicesData = $invoices->getCollection()->map(fn ($inv) => [
            'id' => (string) $inv->id,
            'invoice_no' => $inv->invoice_no,
        ])->values();
    @endphp

    <div class="py-6">
        <div class="max-w-full sm:px-6 lg:px-8"
             x-data="mergeAlpineData(tableSelection({ bulkEndpoint: '{{ route('counter-invoices.bulk-delete') }}', bulkExportEndpoint: '{{ route('counter-invoices.bulk-export') }}' }), counterInvoiceList())">
            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            <x-table-selection-bar
                :bulk-endpoint="route('counter-invoices.bulk-delete')"
                :bulk-export-endpoint="route('counter-invoices.bulk-export')"
                bulkConfirmMessage="Item stock will be returned and related payments will be removed."
            >
                <x-slot name="single">
                    <x-button type="button" @click="$dispatch('open-modal', 'bulk-delete-confirm')" class="flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                        <x-heroicon-o-trash class="w-4 h-4" />
                        Delete
                    </x-button>
                    <x-bulk-delete-confirm
                        :name="'bulk-delete-confirm'"
                        :title="'Delete this invoice?'"
                        :confirm-label="'Delete'"
                        :message="'Item stock will be returned and related payments will be removed.'"
                    />
                </x-slot>
            </x-table-selection-bar>

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
                        <th class="align-middle w-10 px-6 py-3 text-left"><x-table-select-all /></th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Invoice No.</th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Date</th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Customer</th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Payment</th>
                        <th class="align-middle px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Grand Total</th>
                    </tr>
                </x-slot>

                @foreach ($invoices as $invoice)
                    <tr class="hover:bg-muted/50">
                        <td class="align-middle px-6 py-2 whitespace-nowrap">
                            <x-table-checkbox :id="$invoice->id" />
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $invoice->invoice_no }}
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-600">
                            {{ $invoice->invoice_date->format('d/m/Y') }}
                        </td>
                        <td class="align-middle px-6 py-2 text-sm text-gray-600">
                            @if($invoice->customer_id)
                                {{ $invoice->customer->display_name }}
                            @else
                                {{ $invoice->walkin_name ?? 'Walk-in' }}
                                @if($invoice->walkin_mobile)
                                    <br><span class="text-xs text-gray-500">{{ $invoice->walkin_mobile }}</span>
                                @endif
                            @endif
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-xs">
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
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-900 text-right font-medium">
                            {{ config('garage.currency_symbol') }}{{ number_format($invoice->grand_total, 2) }}
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>

    <script>
        function counterInvoiceList() {
            const invoices = @json($invoicesData);
            const base = '{{ url('counter-invoices') }}';

            return {
                base: base,
                invoices: invoices,

                get selectedInvoice() {
                    return this.invoices.find(i => i.id === this.singleId) || null;
                },
            };
        }
    </script>
</x-app-layout>
