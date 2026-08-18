<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center shrink-0">
                    <x-heroicon-o-user class="w-5 h-5 text-primary-600" />
                </div>
                <div>
                    <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                        {{ $customer->display_name }}
                        <x-badge :color="match ($customer->customer_type) {
                            'regular' => 'green',
                            'counter' => 'amber',
                            default => 'gray',
                        }">
                            {{ ucwords(str_replace('_', ' ', $customer->customer_type)) }}
                        </x-badge>
                    </h2>
                    <p class="text-sm text-gray-600 flex flex-wrap gap-x-3 gap-y-0.5">
                        @if ($customer->mobile)
                            <span class="inline-flex items-center gap-1">
                                <x-heroicon-o-device-phone-mobile class="w-3.5 h-3.5 text-gray-400" />
                                {{ $customer->mobile }}
                            </span>
                        @endif
                        @if ($customer->alt_mobile)
                            <span class="inline-flex items-center gap-1">
                                <x-heroicon-o-device-phone-mobile class="w-3.5 h-3.5 text-gray-400" />
                                {{ $customer->alt_mobile }}
                            </span>
                        @endif
                        @if ($customer->email)
                            <span class="inline-flex items-center gap-1">
                                <x-heroicon-o-envelope class="w-3.5 h-3.5 text-gray-400" />
                                {{ $customer->email }}
                            </span>
                        @endif
                        @if ($customer->city)
                            <span class="inline-flex items-center gap-1">
                                <x-heroicon-o-map-pin class="w-3.5 h-3.5 text-gray-400" />
                                {{ $customer->city }}
                            </span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($customer->vehicles_count > 0)
                    <x-button :href="route('job-cards.create', $createJobCardParams)">
                        <x-heroicon-o-wrench-screwdriver class="w-4 h-4 mr-1.5" />
                        Create Job Card
                    </x-button>
                @endif
                <x-button variant="secondary" :href="route('vehicles.create', ['customer_id' => $customer->id])">
                    <x-heroicon-o-plus class="w-4 h-4 mr-1.5" />
                    Add Vehicle
                </x-button>
                @if ($outstandingDocuments->isNotEmpty())
                    <x-button variant="secondary" type="button" @click="$dispatch('open-modal', 'receive-payment')">
                        <x-heroicon-o-banknotes class="w-4 h-4 mr-1.5" />
                        Receive Payment
                    </x-button>
                @else
                    <x-button variant="secondary" type="button" disabled title="No outstanding balance on any document.">
                        <x-heroicon-o-banknotes class="w-4 h-4 mr-1.5" />
                        Receive Payment
                    </x-button>
                @endif
                <x-button variant="secondary" type="button" @click="$dispatch('open-modal', 'customer-statement')">
                    <x-heroicon-o-document-text class="w-4 h-4 mr-1.5" />
                    Statement
                </x-button>
                <x-button variant="secondary" :href="route('customers.edit', $customer)">
                    <x-heroicon-o-pencil-square class="w-4 h-4 mr-1.5" />
                    Edit
                </x-button>
                <x-button variant="ghost" :href="route('customers.index')">
                    Back
                </x-button>
            </div>
        </div>
    </x-slot>

    @php
        $currency = config('garage.currency_symbol');
        $money = fn ($value) => $currency . number_format((float) $value, 2);
        $statementDefaultFrom = (now()->month >= 4 ? now()->year : now()->year - 1) . '-04-01';
        $statementDefaultTo = now()->toDateString();
    @endphp

    <div class="py-6">
        <div class="max-w-full sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-md">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Summary --}}
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <x-card class="!p-5">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Outstanding</div>
                    <div class="mt-1 text-lg font-semibold {{ $customer->outstanding > 0 ? 'text-red-600' : 'text-gray-900' }}">
                        {{ $money($customer->outstanding) }}
                    </div>
                </x-card>
                <x-card class="!p-5">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Paid</div>
                    <div class="mt-1 text-lg font-semibold text-green-600">{{ $money($customer->paid) }}</div>
                </x-card>
                <x-card class="!p-5">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Job Cards</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ $customer->job_cards_count }}</div>
                </x-card>
                <x-card class="!p-5">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Invoices</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ $customer->counter_invoices_count }}</div>
                </x-card>
                <x-card class="!p-5">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicles</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ $customer->vehicles_count }}</div>
                </x-card>
            </div>

            {{-- Tabs --}}
            <div>
                <div class="flex gap-1 border-b border-gray-200 mb-4">
                    <a href="?tab=vehicles"
                       class="px-4 py-2 text-sm font-medium transition-colors duration-150 border-b-2 -mb-px {{ $tab === 'vehicles' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                        Vehicles
                    </a>
                    <a href="?tab=job_cards"
                       class="px-4 py-2 text-sm font-medium transition-colors duration-150 border-b-2 -mb-px {{ $tab === 'job_cards' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                        Job Cards
                    </a>
                    <a href="?tab=financial"
                       class="px-4 py-2 text-sm font-medium transition-colors duration-150 border-b-2 -mb-px {{ $tab === 'financial' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                        Invoices &amp; Payments
                    </a>
                </div>

                {{-- Vehicles tab --}}
                @if ($tab === 'vehicles')
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
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Color</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Odometer</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($customer->vehicles as $vehicle)
                                            <tr>
                                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-900">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-medium">{{ $vehicle->registration_no ?? '—' }}</span>
                                                        @if ($vehicle->is_non_rto)
                                                            <x-badge color="amber">NON-RTO</x-badge>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">
                                                    {{ $vehicle->vehicleModel?->vehicleBrand?->name }} {{ $vehicle->vehicleModel?->name }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">
                                                    {{ ucwords(str_replace('-', ' ', (string) ($vehicle->vehicleModel?->battery_type ?? '—'))) }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">
                                                    {{ $vehicle->color ?? '—' }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600 text-right">
                                                    {{ $vehicle->current_odometer_km ? number_format($vehicle->current_odometer_km) . ' km' : '—' }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-xs text-right">
                                                    <a href="{{ route('vehicles.edit', $vehicle) }}" class="text-primary-600 hover:text-primary-800">Edit</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-card>
                @endif

                {{-- Job Cards / service history tab --}}
                @if ($tab === 'job_cards')
                    <x-data-table
                        :empty="$jobCards->isEmpty()"
                        emptyMessage='No job cards yet. Use "Create Job Card" to open a new job.'
                        emptyIcon="wrench-screwdriver"
                        :emptyHref="route('job-cards.create', $createJobCardParams)"
                        emptyLabel="Create Job Card"
                        :paginator="$jobCards"
                    >
                        <x-slot name="header">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Job Card</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Billed</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Outstanding</th>
                            </tr>
                        </x-slot>
                        @foreach ($jobCards as $jobCard)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-medium">
                                    <a href="{{ route('job-cards.show', $jobCard) }}" class="text-primary-600 hover:text-primary-800">
                                        {{ $jobCard->job_card_no }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">{{ $jobCard->job_date->format('d M Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">{{ $jobCard->vehicle?->registration_no ?? 'NON-RTO' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600 capitalize">{{ str_replace('_', ' ', $jobCard->job_type) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <x-badge :color="match ($jobCard->status) {
                                        'delivered' => 'green',
                                        'ready_for_delivery' => 'primary',
                                        'in_progress' => 'purple',
                                        'waiting_for_parts' => 'amber',
                                        'cancelled' => 'red',
                                        default => 'gray',
                                    }">
                                        {{ ucwords(str_replace('_', ' ', $jobCard->status)) }}
                                    </x-badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-800 text-right">{{ $money($jobCard->grand_total) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600 text-right">{{ $money($jobCard->amount_paid) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-right {{ $jobCard->outstanding > 0 ? 'font-medium text-red-600' : 'text-gray-600' }}">
                                    {{ $money($jobCard->outstanding) }}
                                </td>
                            </tr>
                        @endforeach
                    </x-data-table>
                @endif

                {{-- Invoices & Payments tab --}}
                @if ($tab === 'financial')
                    <div class="space-y-6">
                    <x-data-table
                        :empty="$counterInvoices->isEmpty()"
                        emptyMessage='No counter invoices for this customer.'
                        emptyIcon="receipt-percent"
                        :emptyHref="route('counter-invoices.create')"
                        emptyLabel="New Counter Invoice"
                        :paginator="$counterInvoices"
                    >
                        <x-slot name="header">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice No.</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Billed</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Outstanding</th>
                            </tr>
                        </x-slot>
                        @foreach ($counterInvoices as $invoice)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-medium">
                                    <a href="{{ route('counter-invoices.show', $invoice) }}" class="text-primary-600 hover:text-primary-800">
                                        {{ $invoice->invoice_no }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">{{ $invoice->invoice_date->format('d M Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-800 text-right">{{ $money($invoice->grand_total) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600 text-right">{{ $money($invoice->amount_paid) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-right {{ $invoice->outstanding > 0 ? 'font-medium text-red-600' : 'text-gray-600' }}">
                                    {{ $money($invoice->outstanding) }}
                                </td>
                            </tr>
                        @endforeach
                    </x-data-table>

                    <x-data-table
                        :empty="$payments->isEmpty()"
                        emptyMessage='No payments recorded for this customer.'
                        emptyIcon="banknotes"
                        :paginator="$payments"
                    >
                        <x-slot name="header">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mode</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            </tr>
                        </x-slot>
                        @foreach ($payments as $payment)
                            @php
                                $payable = $payment->payable;
                                $docNo = $payable instanceof \App\Models\JobCard
                                    ? $payable->job_card_no
                                    : $payable?->invoice_no ?? '—';
                                $docRoute = $payable instanceof \App\Models\JobCard
                                    ? route('job-cards.show', $payable)
                                    : ($payable ? route('counter-invoices.show', $payable) : null);
                            @endphp
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-900">{{ $payment->payment_date->format('d M Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-medium">
                                    @if ($docRoute)
                                        <a href="{{ $docRoute }}" class="text-primary-600 hover:text-primary-800">{{ $docNo }}</a>
                                    @else
                                        <span class="text-gray-400 italic">Deleted record</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600 capitalize">{{ str_replace('_', ' ', $payment->payment_mode) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">{{ $payment->reference_no ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-900 font-medium text-right">{{ $money($payment->amount) }}</td>
                            </tr>
                        @endforeach
                    </x-data-table>
                    </div>
                @endif

            </div>
        </div>
    </div>

    {{-- Receive Payment modal --}}
    <x-modal name="receive-payment" maxWidth="lg">
        <form method="POST" action="{{ route('payments.store') }}" class="p-6 space-y-4"
              x-data="{
                  docs: @js($outstandingDocuments),
                  payableKey: '',
                  amount: '',
                  paymentDate: @js(date('Y-m-d')),
                  get payableType() { return this.payableKey.split('|')[0] || ''; },
                  get payableId() { return this.payableKey.split('|')[1] || ''; },
                  get selectedDoc() {
                      const parts = this.payableKey.split('|');
                      return this.docs.find(d => d.type === parts[0] && d.id === parts[1]) || null;
                  },
                  applyOutstanding() {
                      this.amount = this.selectedDoc ? this.selectedDoc.outstanding : '';
                  }
              }">
            @csrf

            @if ($errors->any())
                <div class="bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-md text-sm">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Receive Payment</h3>
                <p class="text-sm text-gray-500 mb-4">Record a payment against one of this customer's outstanding documents.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Document *</label>
                <select x-model="payableKey" @change="applyOutstanding()" required
                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">-- Select document --</option>
                    <template x-for="doc in docs" :key="doc.type + '|' + doc.id">
                        <option :value="doc.type + '|' + doc.id" x-text="doc.label + ' — ' + doc.date + ' (outstanding ' + @js($currency) + doc.outstanding + ')'"></option>
                    </template>
                </select>
            </div>

            <input type="hidden" name="payable_type" :value="payableType">
            <input type="hidden" name="payable_id" :value="payableId">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" x-model="amount"
                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm" required>
                    <p class="text-xs text-gray-500 mt-1" x-show="selectedDoc">
                        Outstanding: <span class="font-medium" x-text="@js($currency) + selectedDoc.outstanding"></span>
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Mode *</label>
                    <select name="payment_mode" required class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">-- Select --</option>
                        @foreach (config('garage.option_lists.payment_mode') as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date *</label>
                    <input type="date" name="payment_date" x-model="paymentDate" required
                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference No</label>
                    <input type="text" name="reference_no" class="block w-full rounded-md border-gray-300 shadow-sm text-sm"
                           placeholder="Txn ID / Cheque No">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="2" class="block w-full rounded-md border-gray-300 shadow-sm text-sm"
                          placeholder="Optional payment notes"></textarea>
            </div>

            <div class="flex justify-end gap-2">
                <x-button variant="secondary" type="button" @click="$dispatch('close-modal', 'receive-payment')">
                    Cancel
                </x-button>
                <x-button variant="success" type="submit">
                    Record Payment
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- Statement modal --}}
    <x-modal name="customer-statement" maxWidth="md">
        <form method="GET" action="{{ route('customers.statement', $customer) }}" class="p-6 space-y-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Customer Statement</h3>
                <p class="text-sm text-gray-500 mb-4">Generate a PDF statement for the selected date range.</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                    <input type="date" name="from_date" value="{{ $statementDefaultFrom }}"
                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                    <input type="date" name="to_date" value="{{ $statementDefaultTo }}"
                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
            </div>
            <p class="text-xs text-gray-500">Defaults to the current financial year (1 April – today).</p>

            <div class="flex justify-end gap-2">
                <x-button variant="secondary" type="button" @click="$dispatch('close-modal', 'customer-statement')">
                    Cancel
                </x-button>
                <x-button type="submit">
                    Generate Statement
                </x-button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
