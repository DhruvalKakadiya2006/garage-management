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

            {{-- Search and Filter Form --}}
            <form method="GET" action="{{ route('counter-invoices.index') }}" class="mb-6">
                <div class="space-y-4">
                    {{-- Filters --}}
                    <x-filter-panel>
                        <div>
                            <label for="from_date" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                            <input type="date" name="from_date" id="from_date" value="{{ request('from_date') }}" 
                                   class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label for="to_date" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                            <input type="date" name="to_date" id="to_date" value="{{ request('to_date') }}" 
                                   class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label for="payment_status" class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                            <select name="payment_status" id="payment_status" class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="all" {{ request('payment_status', 'all') === 'all' ? 'selected' : '' }}>All</option>
                                <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>Paid</option>
                                <option value="partially_paid" {{ request('payment_status') === 'partially_paid' ? 'selected' : '' }}>Partially Paid</option>
                                <option value="unpaid" {{ request('payment_status') === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                            </select>
                        </div>

                        <div>
                            <label for="customer_type" class="block text-sm font-medium text-gray-700 mb-1">Customer Type</label>
                            <select name="customer_type" id="customer_type" class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="all" {{ request('customer_type', 'all') === 'all' ? 'selected' : '' }}>All</option>
                                <option value="registered" {{ request('customer_type') === 'registered' ? 'selected' : '' }}>Registered</option>
                                <option value="walk_in" {{ request('customer_type') === 'walk_in' ? 'selected' : '' }}>Walk-in</option>
                            </select>
                        </div>

                        <div>
                            <label for="per_page" class="block text-sm font-medium text-gray-700 mb-1">Items Per Page</label>
                            <select name="per_page" id="per_page" class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="15" {{ request('per_page', 15) == 15 ? 'selected' : '' }}>15</option>
                                <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <x-button type="submit" class="w-full">
                                <x-heroicon-o-funnel class="w-4 h-4" />
                                Apply Filters
                            </x-button>
                        </div>

                        @if(request()->hasAny(['from_date', 'to_date', 'payment_status', 'customer_type', 'sort']))
                            <div class="flex items-end">
                                <x-button variant="secondary" type="button" onclick="window.location.href='{{ route('counter-invoices.index') }}'" class="w-full">
                                    Clear
                                </x-button>
                            </div>
                        @endif
                    </x-filter-panel>

                    {{-- Active Filters --}}
                    <x-active-filters-bar 
                        :filters="[
                            'from_date' => request('from_date'),
                            'to_date' => request('to_date'),
                            'payment_status' => request('payment_status') !== 'all' ? request('payment_status') : null,
                            'customer_type' => request('customer_type') !== 'all' ? request('customer_type') : null,
                        ]"
                        :baseRoute="'counter-invoices.index'"
                    />
                </div>
            </form>

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
                        <x-sortable-header 
                            column="invoice_no" 
                            label="Invoice No." 
                            :currentSort="request('sort')" 
                            :currentDirection="request('direction', 'asc')"
                        />
                        <x-sortable-header 
                            column="invoice_date" 
                            label="Date" 
                            :currentSort="request('sort')" 
                            :currentDirection="request('direction', 'asc')"
                        />
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Customer</th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Payment</th>
                        <x-sortable-header 
                            column="grand_total" 
                            label="Grand Total" 
                            :currentSort="request('sort')" 
                            :currentDirection="request('direction', 'asc')"
                            class="text-right"
                        />
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
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-600 max-w-[180px] truncate" title="{{ $invoice->customer_id ? $invoice->customer->display_name : ($invoice->walkin_name ?? 'Walk-in') }}">
                            @if($invoice->customer_id)
                                {{ $invoice->customer->display_name }}
                            @else
                                {{ $invoice->walkin_name ?? 'Walk-in' }}@if($invoice->walkin_mobile)<span class="text-gray-400"> · </span><span class="text-xs text-gray-500">{{ $invoice->walkin_mobile }}</span>@endif
                            @endif
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-xs">
                            <span class="inline-flex items-center gap-1">
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
                                    <span class="text-xs text-gray-500">· {{ ucfirst(str_replace('_', ' ', $invoice->payment_mode)) }}</span>
                                @endif
                            </span>
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
