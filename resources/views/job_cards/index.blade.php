<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-wrench-screwdriver class="w-6 h-6 text-primary" />
                Job Cards
            </h2>
            <div class="flex items-center gap-2">
                <x-button variant="secondary" :href="route('import-export.index', ['entity' => 'job_cards'])">
                    <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                    Import
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
                        <x-dropdown-link :href="route('import-export.export', ['entity' => 'job_cards', 'format' => 'csv'])">
                            <span class="inline-flex items-center gap-2">
                                <x-heroicon-o-document-text class="w-4 h-4" />
                                Export All (CSV)
                            </span>
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('import-export.export', array_merge(['entity' => 'job_cards', 'format' => 'csv'], request()->query()))">
                            <span class="inline-flex items-center gap-2">
                                <x-heroicon-o-funnel class="w-4 h-4" />
                                Export Filtered Results (CSV)
                            </span>
                        </x-dropdown-link>
                        <div class="my-1 border-t border-border"></div>
                        <x-dropdown-link :href="route('import-export.template', ['entity' => 'job_cards', 'format' => 'csv'])">
                            <span class="inline-flex items-center gap-2">
                                <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
                                Download Import Template (CSV)
                            </span>
                        </x-dropdown-link>
                    </x-slot>
                </x-dropdown>
                <x-button :href="route('job-cards.create')">
                    <x-heroicon-o-plus class="w-4 h-4" />
                    New Job Card
                </x-button>
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
        <div class="max-w-full sm:px-6 lg:px-8 space-y-4" x-data="jobCardList()">

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

            <!-- Contextual action bar -->
            <div x-show="selectedId" x-transition.opacity.duration.150
                 class="flex flex-wrap items-center gap-3 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 shadow-sm">
                <span class="text-sm font-medium text-primary-800 mr-auto">
                    <span class="text-primary">Selected:</span>
                    <span x-text="selectedCard ? selectedCard.job_card_no : ''" class="font-semibold"></span>
                </span>
                <x-button x-bind:href="assignBase + '/' + selectedId + '/assign'">
                    <x-heroicon-o-wrench-screwdriver class="w-4 h-4" />
                    Assign / Add Items
                </x-button>
                <x-button variant="secondary" x-bind:href="editBase + '/' + selectedId + '/edit'">
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
                <form :action="'{{ url('job-cards') }}' + '/' + selectedId" method="POST"
                      onsubmit="return confirm('Delete this job card? Parts stock will be returned and related payments will be removed.');"
                      class="inline">
                    @csrf
                    @method('DELETE')
                    <x-button variant="danger" type="submit">
                        <x-heroicon-o-trash class="w-4 h-4" />
                        Delete
                    </x-button>
                </form>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl border border-border shadow-sm p-4">
                <form method="GET" action="{{ route('job-cards.index') }}" class="flex flex-wrap gap-4 items-end">
                    <div class="flex-1 min-w-[180px]">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Technician</label>
                        <select name="technician" class="block w-full h-10 rounded-md border-input shadow-sm text-sm">
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
                        <select name="status" class="block w-full h-10 rounded-md border-input shadow-sm text-sm">
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
                        <select name="brand" class="block w-full h-10 rounded-md border-input shadow-sm text-sm">
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
                               class="block w-full h-10 rounded-md border-input shadow-sm text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                        <input type="date" name="to_date" value="{{ request('to_date') }}"
                               class="block w-full h-10 rounded-md border-input shadow-sm text-sm">
                    </div>
                    <div class="flex gap-3">
                        <x-button type="submit">Filter</x-button>
                        <x-button variant="secondary" :href="route('job-cards.index')">Clear</x-button>
                    </div>
                </form>
            </div>

            <x-data-table
                :empty="$jobCards->isEmpty()"
                emptyMessage='No job cards found. Click "New Job Card" to create one.'
                emptyIcon="wrench-screwdriver"
                :emptyHref="route('job-cards.create')"
                emptyLabel="New Job Card"
                :paginator="$jobCards"
            >
                <x-slot name="header">
                    <tr>
                        <th class="w-10 px-6 py-3 text-left"></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Job No.</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Brand</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Model</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Color</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Technician</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Grand Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Payment</th>
                    </tr>
                </x-slot>

                @foreach ($jobCards as $jc)
                    <tr class="group hover:bg-muted/50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="radio" name="job_card" value="{{ $jc->id }}" x-model="selectedId"
                                   class="h-4 w-4 rounded-full border-input text-primary focus:ring-ring cursor-pointer">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="{{ route('job-cards.show', $jc) }}"
                               class="text-primary hover:text-primary-700 transition-colors duration-150">
                                {{ $jc->job_card_no }}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $jc->job_date->format('d M Y') }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $jc->customer->display_name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $jc->vehicle?->vehicleModel?->vehicleBrand?->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $jc->vehicle?->vehicleModel?->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $jc->vehicle->color ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $jc->assignedTechnician->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs">
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">
                            {{ config('garage.currency_symbol') }}{{ number_format((float) $jc->grand_total, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs">
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

    <script>
        function jobCardList() {
            const cards = @json($jobCardsData);
            const base = '{{ url('job-cards') }}';

            return {
                selectedId: '',
                cards: cards,
                assignBase: base,
                editBase: base,

                get selectedCard() {
                    return this.cards.find(c => c.id === this.selectedId) || null;
                },

                get hasLineItems() {
                    return this.selectedCard ? this.selectedCard.has_line_items : false;
                },

                goToDocument(type) {
                    if (this.selectedId && this.hasLineItems) {
                        window.location.href = base + '/' + this.selectedId + '/' + type;
                    }
                },
            };
        }
    </script>
</x-app-layout>
