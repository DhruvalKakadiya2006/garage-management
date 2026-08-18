<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-squares-2x2 class="w-6 h-6 text-primary-600" />
            Dashboard
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @php
                $jobCardStatuses = [
                    'received' => ['gray', 'inbox-arrow-down'],
                    'in_progress' => ['purple', 'clock'],
                    'waiting_for_parts' => ['amber', 'pause-circle'],
                    'ready_for_delivery' => ['primary', 'check-badge'],
                    'delivered' => ['green', 'check-circle'],
                    'cancelled' => ['red', 'x-circle'],
                ];
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                <!-- Today's Job Cards -->
                <x-card class="sm:col-span-2 lg:col-span-2">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="flex items-center gap-2 text-base font-semibold text-gray-900">
                            <x-heroicon-o-wrench-screwdriver class="w-5 h-5 text-primary-600" />
                            Today's Job Cards
                        </h3>
                        <a href="{{ route('job-cards.index') }}"
                           class="inline-flex items-center text-sm font-medium text-primary-600 hover:text-primary-800">
                            View all
                            <x-heroicon-o-arrow-right class="w-4 h-4 ml-1" />
                        </a>
                    </div>

                    <div class="flex items-baseline gap-2 mb-4">
                        <span class="text-3xl font-bold text-gray-900">{{ $todayJobCardsTotal }}</span>
                        <span class="text-sm text-gray-500">received today</span>
                    </div>

                    @if ($todayJobCardsTotal > 0)
                        <div class="flex flex-wrap gap-2">
                            @foreach ($jobCardStatuses as $status => $style)
                                @php $count = $todayJobCards->get($status, 0); @endphp
                                @if ($count > 0)
                                    <x-badge :color="$style[0]">
                                        <x-dynamic-component :component="'heroicon-o-' . $style[1]" class="w-3.5 h-3.5 mr-1" />
                                        {{ ucwords(str_replace('_', ' ', $status)) }}: {{ $count }}
                                    </x-badge>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500">No job cards received today.</p>
                    @endif
                </x-card>

                <!-- This Month's Revenue -->
                <x-card>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <x-heroicon-o-banknotes class="w-5 h-5 text-primary-600" />
                            This Month's Revenue
                        </h3>
                        <a href="{{ route('reports.revenue') }}" title="View revenue report"
                           class="text-gray-400 hover:text-primary-600">
                            <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                        </a>
                    </div>
                    <div class="text-3xl font-bold text-gray-900">
                        {{ config('garage.currency_symbol') }}{{ number_format($monthRevenue, 2) }}
                    </div>
                    <p class="text-sm text-gray-500 mt-2">Delivered job cards + counter invoices ({{ now()->format('F Y') }})</p>
                </x-card>

                <!-- Low Stock Alert -->
                <x-card>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <x-heroicon-o-cube class="w-5 h-5 text-primary-600" />
                            Low Stock Alert
                        </h3>
                        <a href="{{ route('spare-parts.index', ['low_stock' => 1]) }}" title="View low stock parts"
                           class="text-gray-400 hover:text-primary-600">
                            <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                        </a>
                    </div>
                    <div class="text-3xl font-bold {{ $lowStockCount > 0 ? 'text-red-600' : 'text-gray-900' }}">
                        {{ $lowStockCount }}
                    </div>
                    <p class="text-sm {{ $lowStockCount > 0 ? 'text-red-600' : 'text-gray-500' }} mt-2">
                        part(s) at or below reorder level
                    </p>
                </x-card>

                <!-- Outstanding Dues -->
                <x-card>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-primary-600" />
                            Outstanding Dues
                        </h3>
                        <a href="{{ route('reports.payments') }}" title="View payments report"
                           class="text-gray-400 hover:text-primary-600">
                            <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                        </a>
                    </div>
                    <div class="text-3xl font-bold {{ $outstandingDues > 0 ? 'text-red-600' : 'text-gray-900' }}">
                        {{ config('garage.currency_symbol') }}{{ number_format($outstandingDues, 2) }}
                    </div>
                    <p class="text-sm {{ $outstandingDues > 0 ? 'text-red-600' : 'text-gray-500' }} mt-2">
                        unpaid across job cards &amp; invoices
                    </p>
                </x-card>

                <!-- Quick Actions -->
                <x-card>
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 mb-4">
                        <x-heroicon-o-bolt class="w-5 h-5 text-primary-600" />
                        Quick Actions
                    </h3>
                    <div class="space-y-3">
                        <x-button :href="route('job-cards.create')" class="w-full">
                            <x-heroicon-o-plus class="w-4 h-4 mr-1.5" />
                            New Job Card
                        </x-button>
                        <x-button variant="success" :href="route('counter-invoices.create')" class="w-full">
                            <x-heroicon-o-plus class="w-4 h-4 mr-1.5" />
                            New Counter Invoice
                        </x-button>
                    </div>
                </x-card>

                <!-- Recent Job Cards -->
                <x-card class="sm:col-span-2 lg:col-span-2">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="flex items-center gap-2 text-base font-semibold text-gray-900">
                            <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-primary-600" />
                            Recent Job Cards
                        </h3>
                        <a href="{{ route('job-cards.index') }}"
                           class="inline-flex items-center text-sm font-medium text-primary-600 hover:text-primary-800">
                            View all
                            <x-heroicon-o-arrow-right class="w-4 h-4 ml-1" />
                        </a>
                    </div>

                    @if ($recentJobCards->isEmpty())
                        <p class="text-sm text-gray-500">No job cards yet. Create one from Quick Actions.</p>
                    @else
                        <ul class="divide-y divide-border">
                            @foreach ($recentJobCards as $jc)
                                <li class="flex items-center justify-between gap-3 py-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="font-medium text-sm text-gray-900 shrink-0">{{ $jc->job_card_no }}</span>
                                        <span class="text-sm text-gray-500 truncate">{{ $jc->customer->display_name ?? '-' }}</span>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <x-badge :color="$jobCardStatuses[$jc->status][0] ?? 'gray'">
                                            <x-dynamic-component :component="'heroicon-o-' . ($jobCardStatuses[$jc->status][1] ?? 'inbox-arrow-down')" class="w-3.5 h-3.5 mr-1" />
                                            {{ ucwords(str_replace('_', ' ', $jc->status)) }}
                                        </x-badge>
                                        <a href="{{ route('job-cards.show', $jc) }}" title="View"
                                           class="text-primary-600 hover:text-primary-800">
                                            <x-heroicon-o-eye class="w-5 h-5" />
                                        </a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

            </div>
        </div>
    </div>
</x-app-layout>
