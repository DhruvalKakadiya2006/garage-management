<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-banknotes class="w-6 h-6 text-primary" />
                Revenue Analytics
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
                :action="route('analytics.revenue')" 
            />

            <!-- Total Revenue Card -->
            <div class="bg-gradient-to-r from-green-500 to-green-600 shadow-lg rounded-xl p-8 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm uppercase tracking-wide mb-2">Total Revenue</p>
                        <p class="text-4xl font-bold">{{ config('garage.currency_symbol') }}{{ number_format($totalRevenue, 2) }}</p>
                        <p class="text-green-100 text-sm mt-2">
                            {{ \Carbon\Carbon::parse($fromDate)->format('d/m/Y') }} 
                            to 
                            {{ \Carbon\Carbon::parse($toDate)->format('d/m/Y') }}
                        </p>
                    </div>
                    <div class="opacity-50">
                        <x-heroicon-o-banknotes class="w-16 h-16" />
                    </div>
                </div>
            </div>

            <!-- Revenue Breakdown by Source -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white shadow-sm rounded-xl p-6">
                    <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="text-primary">
                            <x-heroicon-o-wrench-screwdriver class="w-5 h-5" />
                        </span>
                        Job Cards Revenue
                    </h3>
                    <div class="text-3xl font-bold text-gray-900">
                        {{ config('garage.currency_symbol') }}{{ number_format($jobCardsRevenue, 2) }}
                    </div>
                    <p class="text-sm text-gray-600 mt-2">
                        {{ $totalRevenue > 0 ? number_format(($jobCardsRevenue / $totalRevenue) * 100, 1) : 0 }}% of total revenue
                    </p>
                    <p class="text-xs text-gray-500 mt-2">
                        Only delivered job cards are counted
                    </p>
                </div>

                <div class="bg-white shadow-sm rounded-xl p-6">
                    <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="text-green-500">
                            <x-heroicon-o-shopping-cart class="w-5 h-5" />
                        </span>
                        Counter Invoices Revenue
                    </h3>
                    <div class="text-3xl font-bold text-gray-900">
                        {{ config('garage.currency_symbol') }}{{ number_format($counterInvoicesRevenue, 2) }}
                    </div>
                    <p class="text-sm text-gray-600 mt-2">
                        {{ $totalRevenue > 0 ? number_format(($counterInvoicesRevenue / $totalRevenue) * 100, 1) : 0 }}% of total revenue
                    </p>
                    <p class="text-xs text-gray-500 mt-2">
                        All counter sales included
                    </p>
                </div>
            </div>

            <!-- Revenue by Job Type -->
            @if($revenueByJobType->count() > 0)
            <div class="bg-white shadow-sm rounded-xl p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Revenue Breakdown by Job Type</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Job Type</th>
                                <th class="align-middle px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">Revenue</th>
                                <th class="align-middle px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">% of Job Cards</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach($revenueByJobType as $item)
                            <tr class="hover:bg-muted/50">
                                <td class="align-middle px-6 py-2 whitespace-nowrap capitalize">
                                    {{ str_replace('_', ' ', $item->job_type) }}
                                </td>
                                <td class="align-middle px-6 py-2 whitespace-nowrap text-right font-medium">
                                    {{ config('garage.currency_symbol') }}{{ number_format($item->total, 2) }}
                                </td>
                                <td class="align-middle px-6 py-2 whitespace-nowrap text-right text-gray-600">
                                    {{ $jobCardsRevenue > 0 ? number_format(($item->total / $jobCardsRevenue) * 100, 1) : 0 }}%
                                </td>
                            </tr>
                            @endforeach
                            <tr class="bg-muted font-semibold">
                                <td class="align-middle px-6 py-2">Total (Job Cards)</td>
                                <td class="align-middle px-6 py-2 text-right">{{ config('garage.currency_symbol') }}{{ number_format($jobCardsRevenue, 2) }}</td>
                                <td class="align-middle px-6 py-2 text-right">100%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <!-- Daily Revenue Trend -->
            <div class="bg-white shadow-sm rounded-xl p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Daily Revenue Trend</h3>
                <div class="mb-4 text-sm text-gray-600">
                    <p>Visualizing revenue (Job Cards + Counter Invoices) for each day in the selected range.</p>
                </div>
                <canvas id="revenueChart" class="w-full" style="max-height: 400px;"></canvas>
            </div>

            <!-- 6-Month Revenue Trend -->
            <div class="bg-white shadow-sm rounded-xl p-6">
                <h3 class="font-semibold text-gray-900 mb-4">6-Month Revenue Trend</h3>
                <div class="mb-4 text-sm text-gray-600">
                    <p>Rolling 6-month revenue trajectory, independent of the selected date range, showing overall business direction.</p>
                </div>
                <canvas id="revenueTrendChart" class="w-full" style="max-height: 400px;"></canvas>
            </div>

            @if($totalRevenue == 0)
            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-6 text-center">
                <p class="font-medium">No revenue data found for this date range.</p>
                <p class="text-sm mt-2">Try selecting a different date range or check if any job cards are marked as "delivered".</p>
            </div>
            @endif

        </div>
    </div>

    <script>
        // Chart.js is bundled via Vite (resources/js/app.js) — no CDN dependency.
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Chart === 'undefined') {
                return;
            }

            // --- Daily Revenue Chart ---
            const dailyLabels = @json($revenueByDay->pluck('date'));
            const dailyData = @json($revenueByDay->pluck('total'));

            const ctx1 = document.getElementById('revenueChart').getContext('2d');
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: dailyLabels,
                    datasets: [{
                        label: 'Daily Revenue ({{ config('garage.currency_symbol') }})',
                        data: dailyData,
                        backgroundColor: 'rgba(34, 197, 94, 0.7)',
                        borderColor: 'rgba(34, 197, 94, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += '{{ config('garage.currency_symbol') }}' + context.parsed.y.toLocaleString('en-IN', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '{{ config('garage.currency_symbol') }}' + value.toLocaleString('en-IN');
                                }
                            }
                        }
                    }
                }
            });

            // --- 6-Month Revenue Trend Chart ---
            const trendLabels = @json($revenueTrend->pluck('label'));
            const trendData = @json($revenueTrend->pluck('total'));

            const ctx2 = document.getElementById('revenueTrendChart').getContext('2d');
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: '6-Month Revenue ({{ config('garage.currency_symbol') }})',
                        data: trendData,
                        borderColor: 'rgba(59, 130, 246, 1)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.3,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += '{{ config('garage.currency_symbol') }}' + context.parsed.y.toLocaleString('en-IN', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '{{ config('garage.currency_symbol') }}' + value.toLocaleString('en-IN');
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</x-app-layout>
