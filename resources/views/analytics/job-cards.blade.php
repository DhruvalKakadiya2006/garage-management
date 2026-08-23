<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-wrench-screwdriver class="w-6 h-6 text-primary" />
                Job Cards Analytics
            </h2>
            <a href="{{ route('analytics.index') }}" 
               class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-800">
                <x-heroicon-o-arrow-left class="w-4 h-4" />
                Back to Analytics
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Date Range Filter -->
            <x-date-range-filter 
                :fromDate="$fromDate" 
                :toDate="$toDate" 
                :action="route('analytics.job_cards')" 
            />

            <!-- Summary Row -->
            <div class="bg-white shadow-sm rounded-xl p-6 border-b border-border">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="bg-gradient-to-br from-primary-500 to-primary-600 text-white p-4 rounded-lg">
                        <p class="text-xs uppercase tracking-wide mb-1">Average Job Value</p>
                        <p class="text-2xl font-bold">₹{{ number_format($averageJobValue, 2) }}</p>
                        <p class="text-sm font-medium mt-1">per delivered job card</p>
                    </div>

                    <div class="bg-gradient-to-br from-green-500 to-green-600 text-white p-4 rounded-lg">
                        <p class="text-xs uppercase tracking-wide mb-1">Delivered Cards</p>
                        <p class="text-2xl font-bold">{{ $deliveredCount }}</p>
                        <p class="text-sm font-medium mt-1">in selected range</p>
                    </div>

                    <div class="bg-gradient-to-br from-green-500 to-green-600 text-white p-4 rounded-lg">
                        <p class="text-xs uppercase tracking-wide mb-1">Delivered Revenue</p>
                        <p class="text-2xl font-bold">₹{{ number_format($deliveredRevenue, 2) }}</p>
                        <p class="text-sm font-medium mt-1">total for delivered cards</p>
                    </div>
                </div>
            </div>

            <!-- By Status -->
            <div class="bg-white shadow-sm rounded-xl p-6">
                <h3 class="font-semibold text-gray-900 mb-4">By Status</h3>
                @if($statusCounts->isEmpty())
                    <p class="text-sm text-gray-500">No job cards found in this date range.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">Count</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">% of Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($statusCounts as $item)
                                <tr class="hover:bg-muted/50">
                                    <td class="px-6 py-4 whitespace-nowrap capitalize">
                                        {{ str_replace('_', ' ', $item->status) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right font-medium">
                                        {{ $item->count }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-gray-600">
                                        {{ $totalJobCards > 0 ? number_format(($item->count / $totalJobCards) * 100, 1) : 0 }}%
                                    </td>
                                </tr>
                                @endforeach
                                <tr class="bg-muted font-semibold">
                                    <td class="px-6 py-4">Total</td>
                                    <td class="px-6 py-4 text-right">{{ $totalJobCards }}</td>
                                    <td class="px-6 py-4 text-right">100%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif

<!-- Status Doughnut Chart -->
                <div class="mt-6">
                    <h4 class="font-medium text-gray-900 mb-3">Status Distribution</h4>
                    <canvas id="statusDoughnutChart" class="w-full" style="max-height: 400px;"></canvas>

                    <script>
                        // Map status codes to human-readable labels from config
                        @php
                        $configKeys = array_keys(config('garage.option_lists.job_card_status'));
                        $statusLabels = config('garage.option_lists.job_card_status');
                        @endphp
                        const statusLabels = @json($statusLabels);
                        // Build ordered array of status keys matching config
                        const configKeys = @json($configKeys);
                        // Build data array: count per status, matching config key order
                        const statusCountsData = [
                            @foreach($configKeys as $i => $key)
                            {{ $statusCounts->where('status', $key)->first()?->count ?? 0 }}{{ $loop->last ? '' : ',' }}
                            @endforeach
                        ];
                        const total = {{ $totalJobCards }};

                        document.addEventListener('DOMContentLoaded', () => {
                            if (typeof Chart === 'undefined') {
                                return;
                            }

                            const statusData = configKeys.map((key, i) => {
                                const count = parseInt(statusCountsData[i] || '0');
                                const label = statusLabels[key];
                                return {
                                    label: label,
                                    count: count,
                                    percentage: total > 0 ? (count / total * 100).toFixed(1) : 0,
                                    backgroundColor: i < 5 ? [
                                        'rgba(34, 197, 94, 0.7)',
                                        'rgba(59, 130, 246, 0.7)',
                                        'rgba(251, 191, 36, 0.7)',
                                        'rgba(239, 68, 68, 0.7)',
                                        'rgba(168, 85, 247, 0.7)'
                                    ][i] : 'rgba(107, 114, 128, 0.7)'
                                };
                            });

                            const doughnutCtx = document.getElementById('statusDoughnutChart').getContext('2d');
                            new Chart(doughnutCtx, {
                                type: 'doughnut',
                                data: {
                                    labels: statusData.map(d => d.label),
                                    datasets: [{
                                        data: statusData.map(d => d.count),
                                        backgroundColor: statusData.map(d => d.backgroundColor)
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'bottom',
                                            labels: {
                                                usePointStyle: true,
                                                pointStyle: 'rect',
                                                font: {
                                                    size: 10
                                                }
                                            }
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    let label = context.label || '';
                                                    if (label) {
                                                        label += ': ';
                                                    }
                                                    label += context.parsed +
                                                        ' (' + context.percentage + '%)';
                                                    return label;
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        });
                    </script>
                </div>
            </div>

            <!-- By Job Type -->
            <div class="bg-white shadow-sm rounded-xl p-6">
                <h3 class="font-semibold text-gray-900 mb-4">By Job Type</h3>
                @if($jobTypeCounts->isEmpty())
                    <p class="text-sm text-gray-500">No job cards found in this date range.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Job Type</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">Count</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">% of Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($jobTypeCounts as $item)
                                <tr class="hover:bg-muted/50">
                                    <td class="px-6 py-4 whitespace-nowrap capitalize">
                                        {{ str_replace('_', ' ', $item->job_type) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right font-medium">
                                        {{ $item->count }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-gray-600">
                                        {{ $totalJobCards > 0 ? number_format(($item->count / $totalJobCards) * 100, 1) : 0 }}%
                                    </td>
                                </tr>
                                @endforeach
                                <tr class="bg-muted font-semibold">
                                    <td class="px-6 py-4">Total</td>
                                    <td class="px-6 py-4 text-right">{{ $totalJobCards }}</td>
                                    <td class="px-6 py-4 text-right">100%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>