<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-wrench-screwdriver class="w-6 h-6 text-primary" />
                Job Cards
            </h2>
            <div class="flex items-center gap-2">
                <x-button variant="secondary" type="button" @click="$dispatch('open-modal', 'import-csv-job-cards')">
                    <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                    Import CSV
                </x-button>
                <x-dropdown align="left" width="64">
                    <x-slot name="trigger">
                        <x-button variant="secondary" type="button">
                            <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                            Export
                            <x-heroicon-o-chevron-down class="w-4 h-4" />
                        </x-button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('import-export.export', ['entity' => 'job_cards'])">
                            <span class="inline-flex items-center gap-2">
                                <x-heroicon-o-document-text class="w-4 h-4" />
                                Export All (CSV)
                            </span>
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('import-export.export', array_merge(['entity' => 'job_cards'], request()->query()))">
                            <span class="inline-flex items-center gap-2">
                                <x-heroicon-o-funnel class="w-4 h-4" />
                                Export Filtered Results (CSV)
                            </span>
                        </x-dropdown-link>
                        <div class="my-1 border-t border-border"></div>
                        <x-dropdown-link :href="route('import-export.template', ['entity' => 'job_cards'])">
                            <span class="inline-flex items-center gap-2">
                                <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
                                Download Import Template (CSV)
                            </span>
                        </x-dropdown-link>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </x-slot>

    @php
        $jobCardsData = $jobCards->getCollection()->map(fn ($jc) => [
            'id' => (string) $jc->id,
            'job_card_no' => $jc->job_card_no,
            'has_line_items' => (bool) ($jc->parts_exists || $jc->labours_exists),
        ])->values();
    @endphp

    <div class="py-6">
        <div class="max-w-full sm:px-6 lg:px-8 space-y-4"
             x-data="mergeAlpineData(tableSelection({ bulkEndpoint: '{{ route('job-cards.bulk-delete') }}', bulkExportEndpoint: '{{ route('job-cards.bulk-export') }}' }), jobCardList())">

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

            @if ($errors->any())
                <div class="bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-md">
                    <ul class="list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Search and Filter Form --}}
            <form method="GET" action="{{ route('job-cards.index') }}" class="mb-6">
                <div class="space-y-4">
                    {{-- Search Bar --}}
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <x-search-bar 
                                name="search" 
                                placeholder="Search by job card no, customer name, or vehicle registration..." 
                                :value="request('search')"
                            />
                        </div>
                        <x-button type="submit">
                            <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                            Search
                        </x-button>
                        @if(request()->hasAny(['search', 'from_date', 'to_date', 'technician', 'status', 'brand', 'payment_status', 'job_type', 'sort']))
                            <x-button variant="secondary" type="button" onclick="window.location.href='{{ route('job-cards.index') }}'">
                                Clear
                            </x-button>
                        @endif
                    </div>

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
                            <label for="technician" class="block text-sm font-medium text-gray-700 mb-1">Technician</label>
                            <select name="technician" id="technician" class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Technicians</option>
                                @foreach($technicians as $tech)
                                    <option value="{{ $tech->id }}" {{ request('technician') == $tech->id ? 'selected' : '' }}>
                                        {{ $tech->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" id="status" class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Statuses</option>
                                @foreach(config('garage.option_lists.job_card_status') as $value => $label)
                                    <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="brand" class="block text-sm font-medium text-gray-700 mb-1">Vehicle Brand</label>
                            <select name="brand" id="brand" class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Brands</option>
                                @foreach($brands as $brand)
                                    <option value="{{ $brand }}" {{ request('brand') === $brand ? 'selected' : '' }}>
                                        {{ $brand }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="payment_status" class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                            <select name="payment_status" id="payment_status" class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="all" {{ request('payment_status', 'all') === 'all' ? 'selected' : '' }}>All</option>
                                <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>Paid</option>
                                <option value="partially_paid" {{ request('payment_status') === 'partially_paid' ? 'selected' : '' }}>Partially Paid</option>
                                <option value="unbilled" {{ request('payment_status') === 'unbilled' ? 'selected' : '' }}>Unbilled</option>
                                <option value="unpaid" {{ request('payment_status') === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                            </select>
                        </div>

                        <div>
                            <label for="job_type" class="block text-sm font-medium text-gray-700 mb-1">Job Type</label>
                            <select name="job_type" id="job_type" class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Types</option>
                                @foreach(config('garage.option_lists.job_type') as $value => $label)
                                    <option value="{{ $value }}" {{ request('job_type') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
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
                    </x-filter-panel>

                    {{-- Active Filters --}}
                    <x-active-filters-bar 
                        :filters="[
                            'search' => request('search'),
                            'from_date' => request('from_date'),
                            'to_date' => request('to_date'),
                            'technician' => request('technician') ? $technicians->find(request('technician'))?->name : null,
                            'status' => request('status'),
                            'brand' => request('brand'),
                            'payment_status' => request('payment_status') !== 'all' ? request('payment_status') : null,
                            'job_type' => request('job_type'),
                        ]"
                        :baseRoute="'job-cards.index'"
                    />
                </div>
            </form>

            <!-- Contextual action bar -->
            <x-table-selection-bar
                :bulk-endpoint="route('job-cards.bulk-delete')"
                :bulk-export-endpoint="route('job-cards.bulk-export')"
                bulkConfirmMessage="Parts stock will be returned and related payments will be removed."
            >
                <x-slot name="single">
                    <span class="text-sm font-medium text-primary-800">
                        Selected:
                        <span x-text="selectedCard ? selectedCard.job_card_no : ''" class="font-semibold"></span>
                    </span>
                    <x-button x-bind:href="assignBase + '/' + singleId + '/assign'">
                        <x-heroicon-o-wrench-screwdriver class="w-4 h-4" />
                        Assign / Add Items
                    </x-button>
                    <x-button variant="secondary" x-bind:href="editBase + '/' + singleId + '/edit'">
                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                        Edit
                    </x-button>
                    <x-button variant="secondary" type="button" @click="goToDocument('invoice')" x-bind:disabled="!hasLineItems"
                            x-bind:title="hasLineItems ? 'Download TAX INVOICE PDF' : 'Add at least one part or labour line to generate'">
                        <x-heroicon-o-document-text class="w-4 h-4" />
                        Generate Invoice
                    </x-button>
                    <x-button variant="secondary" type="button" @click="goToDocument('estimate')" x-bind:disabled="!hasLineItems"
                            x-bind:title="hasLineItems ? 'Download ESTIMATE PDF' : 'Add at least one part or labour line to generate'">
                        <x-heroicon-o-document-chart-bar class="w-4 h-4" />
                        Generate Estimate
                    </x-button>
                </x-slot>
            </x-table-selection-bar>

            <!-- Filters -->
            <div class="bg-slate-50 rounded-xl border border-slate-200 shadow-sm p-4">
                <form method="GET" action="{{ route('job-cards.index') }}" class="flex flex-wrap gap-4 items-end">
                    <div class="flex-1 min-w-[180px]">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Technician</label>
                        <select name="technician" class="block w-full h-10 rounded-md border border-gray-300 bg-white shadow-sm text-sm focus:border-primary focus:ring-primary">
                            <option value="">-- All Technicians --</option>
                            @foreach ($technicians as $tech)
                                <option value="{{ $tech->id }}" {{ request('technician') == $tech->id ? 'selected' : '' }}>
                                    {{ $tech->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1 min-w-[180px]">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="block w-full h-10 rounded-md border border-gray-300 bg-white shadow-sm text-sm focus:border-primary focus:ring-primary">
                            <option value="">-- All Statuses --</option>
                            @foreach (config('garage.option_lists.job_card_status') as $statusValue => $statusLabel)
                                <option value="{{ $statusValue }}" {{ request('status') == $statusValue ? 'selected' : '' }}>
                                    {{ $statusLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1 min-w-[180px]">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Brand</label>
                        <select name="brand" class="block w-full h-10 rounded-md border border-gray-300 bg-white shadow-sm text-sm focus:border-primary focus:ring-primary">
                            <option value="">-- All Brands --</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand }}" {{ request('brand') == $brand ? 'selected' : '' }}>
                                    {{ $brand }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                        <input type="date" name="from_date" value="{{ request('from_date') }}"
                               class="block w-full h-10 rounded-md border border-gray-300 bg-white shadow-sm text-sm focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                        <input type="date" name="to_date" value="{{ request('to_date') }}"
                               class="block w-full h-10 rounded-md border border-gray-300 bg-white shadow-sm text-sm focus:border-primary focus:ring-primary">
                    </div>
                    <div class="flex gap-3">
                        <x-button type="submit">Filter</x-button>
                        <x-button variant="secondary" :href="route('job-cards.index')">Clear</x-button>
                    </div>
                </form>
            </div>

            <x-data-table
                :empty="$jobCards->isEmpty()"
                emptyMessage='No job cards yet. Create one from a customer&#039;s page.'
                emptyIcon="wrench-screwdriver"
                :paginator="$jobCards"
            >
                <x-slot name="header">
                    <tr>
                        <th class="align-middle w-10 px-6 py-3 text-left"><x-table-select-all /></th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Job No.</th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Date</th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Customer</th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Brand</th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Model</th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Color</th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Technician</th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Status</th>
                        <th class="align-middle px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Grand Total</th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Payment</th>
                    </tr>
                </x-slot>

                @foreach ($jobCards as $jc)
                    <tr class="group hover:bg-muted/50">
                        <td class="align-middle px-6 py-2 whitespace-nowrap">
                            <x-table-checkbox :id="$jc->id" />
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm font-medium">
                            <a href="{{ route('job-cards.show', $jc) }}"
                               class="text-primary hover:text-primary-700 transition-colors duration-150">
                                {{ $jc->job_card_no }}
                            </a>
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-600">
                            {{ $jc->job_date->format('d/m/Y') }}
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-600 max-w-[180px] truncate" title="{{ $jc->customer->display_name ?? '-' }}">
                            {{ $jc->customer->display_name ?? '-' }}
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-600">
                            {{ $jc->vehicle?->vehicleModel?->vehicleBrand?->name ?? '-' }}
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-600">
                            {{ $jc->vehicle?->vehicleModel?->name ?? '-' }}
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-600">
                            {{ $jc->vehicle->color ?? '-' }}
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-600 max-w-[140px] truncate" title="{{ $jc->assignedTechnician->name ?? '-' }}">
                            {{ $jc->assignedTechnician->name ?? '-' }}
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-xs">
                            <x-badge :color="match ($jc->status) {
                                'delivered' => 'green',
                                'ready_for_delivery' => 'primary',
                                'in_progress' => 'purple',
                                'waiting_for_parts' => 'amber',
                                'cancelled' => 'red',
                                default => 'gray',
                            }">
                                @if($jc->status === 'delivered')
                                    <x-heroicon-o-check-circle class="w-3.5 h-3.5" />
                                @elseif($jc->status === 'ready_for_delivery')
                                    <x-heroicon-o-check-badge class="w-3.5 h-3.5" />
                                @elseif($jc->status === 'in_progress')
                                    <x-heroicon-o-clock class="w-3.5 h-3.5" />
                                @elseif($jc->status === 'waiting_for_parts')
                                    <x-heroicon-o-pause-circle class="w-3.5 h-3.5" />
                                @elseif($jc->status === 'cancelled')
                                    <x-heroicon-o-x-circle class="w-3.5 h-3.5" />
                                @else
                                    <x-heroicon-o-inbox-arrow-down class="w-3.5 h-3.5" />
                                @endif
                                {{ ucwords(str_replace('_', ' ', $jc->status)) }}
                            </x-badge>
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-900 text-right font-medium">
                            {{ config('garage.currency_symbol') }}{{ number_format((float) $jc->grand_total, 2) }}
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-xs">
                            <x-badge :color="match ($jc->payment_status_label) {
                                'paid' => 'green',
                                'partially_paid' => 'amber',
                                'unbilled' => 'gray',
                                default => 'red',
                            }">
                                @if($jc->payment_status_label === 'paid')
                                    <x-heroicon-o-check-circle class="w-3.5 h-3.5" />
                                @elseif($jc->payment_status_label === 'partially_paid')
                                    <x-heroicon-o-clock class="w-3.5 h-3.5" />
                                @elseif($jc->payment_status_label === 'unbilled')
                                    <x-heroicon-o-inbox class="w-3.5 h-3.5" />
                                @else
                                    <x-heroicon-o-x-circle class="w-3.5 h-3.5" />
                                @endif
                                {{ ucwords(str_replace('_', ' ', $jc->payment_status_label)) }}
                            </x-badge>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>

    <x-import-csv-modal name="import-csv-job-cards" entity="job_cards" title="Import Job Cards" />

    <script>
        function jobCardList() {
            const cards = @json($jobCardsData);
            const base = '{{ url('job-cards') }}';

            return {
                cards: cards,
                assignBase: base,
                editBase: base,

                get selectedCard() {
                    return this.cards.find(c => c.id === this.singleId) || null;
                },

                get hasLineItems() {
                    return this.selectedCard ? this.selectedCard.has_line_items : false;
                },

                goToDocument(type) {
                    if (this.singleId && this.hasLineItems) {
                        window.location.href = base + '/' + this.singleId + '/' + type;
                    }
                },
            };
        }
    </script>
</x-app-layout>
