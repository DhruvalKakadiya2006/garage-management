<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="flex items-center gap-3 font-semibold text-2xl tracking-tight">
                <x-heroicon-o-chart-bar class="w-7 h-7 text-primary" />
                Analytics
            </h2>
            <div class="text-sm text-muted-foreground">
                {{ \Carbon\Carbon::now()->format('F Y') }}
            </div>
        </div>
    </x-slot>

    <div class="py-8 bg-gradient-to-b from-muted/30 to-background min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            {{-- KPI Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-gradient-to-br from-emerald-50 to-white dark:from-emerald-950/20 dark:to-card border border-emerald-200 dark:border-emerald-900 rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-400">Revenue This Month</p>
                            <p class="text-3xl font-bold text-emerald-900 dark:text-emerald-100 mt-2">₹{{ number_format($revenueThisMonth, 2) }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-emerald-500/20 flex items-center justify-center">
                            <x-heroicon-o-currency-rupee class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-blue-50 to-white dark:from-blue-950/20 dark:to-card border border-blue-200 dark:border-blue-900 rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-blue-700 dark:text-blue-400">Open Job Cards</p>
                            <p class="text-3xl font-bold text-blue-900 dark:text-blue-100 mt-2">{{ $openJobCardsCount }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-blue-500/20 flex items-center justify-center">
                            <x-heroicon-o-wrench-screwdriver class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-rose-50 to-white dark:from-rose-950/20 dark:to-card border border-rose-200 dark:border-rose-900 rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-rose-700 dark:text-rose-400">Outstanding Balance</p>
                            <p class="text-3xl font-bold text-rose-900 dark:text-rose-100 mt-2">₹{{ number_format($totalOutstanding, 2) }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-rose-500/20 flex items-center justify-center">
                            <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-rose-600 dark:text-rose-400" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- Revenue Section --}}
            <div class="space-y-6">
                <div class="flex items-center gap-2">
                    <div class="h-1 w-1 rounded-full bg-primary"></div>
                    <h3 class="text-sm font-semibold tracking-wider uppercase text-muted-foreground">Revenue & Sales</h3>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-card border rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between mb-6">
                            <h4 class="text-base font-semibold">Revenue Trend</h4>
                            <select id="revTrendRange" class="text-sm border border-input bg-background rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-ring/50 transition-shadow">
                                <option value="monthly" selected>Monthly (6m)</option>
                                <option value="weekly">Weekly (12w)</option>
                                <option value="daily">Daily (30d)</option>
                            </select>
                        </div>
                        <div class="relative h-[300px]">
                            <canvas id="revTrendChart"></canvas>
                        </div>
                        <p class="text-xs text-muted-foreground mt-4 flex items-center gap-1">
                            <span class="inline-block w-2 h-2 rounded-full bg-primary"></span>
                            Per-period total (not cumulative) · Job Cards + Counter Invoices
                        </p>
                    </div>

                    <div class="bg-card border rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <h4 class="text-base font-semibold mb-6">Revenue Split</h4>
                        <div class="relative h-[220px] flex items-center justify-center">
                            <canvas id="revSourceChart"></canvas>
                        </div>
                        <div class="mt-6 space-y-3 text-sm">
                            <div class="flex justify-between items-center">
                                <span class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-sm bg-[#6366f1]"></span>
                                    <span class="text-muted-foreground">Job Cards</span>
                                </span>
                                <span class="font-semibold">₹{{ number_format($jobCardsRevenueMonth, 2) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-sm bg-[#16a34a]"></span>
                                    <span class="text-muted-foreground">Counter Invoices</span>
                                </span>
                                <span class="font-semibold">₹{{ number_format($counterRevenueMonth, 2) }}</span>
                            </div>
                            <div class="flex justify-between items-center pt-3 border-t font-bold">
                                <span>Total</span>
                                <span class="text-lg">₹{{ number_format($revenueThisMonth, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-card border rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <h4 class="text-sm font-semibold mb-5">Revenue by Job Type</h4>
                        @if($revenueByJobType->isEmpty())
                            <p class="text-sm text-muted-foreground py-8 text-center">No delivered jobs this month.</p>
                        @else
                            <div class="relative h-[220px]">
                                <canvas id="revJobTypeChart"></canvas>
                            </div>
                        @endif
                    </div>

                    <div class="bg-card border rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <h4 class="text-sm font-semibold mb-5">Average Job Value Trend</h4>
                        <div class="relative h-[220px]">
                            <canvas id="avgJobValueChart"></canvas>
                        </div>
                        <p class="text-xs text-muted-foreground mt-4 flex items-center gap-1">
                            <span class="inline-block w-2 h-2 rounded-full bg-orange-500"></span>
                            Delivered jobs only · last 6 months
                        </p>
                    </div>

                    <div class="bg-card border rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <h4 class="text-sm font-semibold mb-5">Payment Modes</h4>
                        @if($paymentModeBreakdown->isEmpty())
                            <p class="text-sm text-muted-foreground py-8 text-center">No payments this month.</p>
                        @else
                            <div class="relative h-[220px] flex items-center justify-center">
                                <canvas id="payModeChart"></canvas>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Operations Section --}}
            <div class="space-y-6">
                <div class="flex items-center gap-2">
                    <div class="h-1 w-1 rounded-full bg-primary"></div>
                    <h3 class="text-sm font-semibold tracking-wider uppercase text-muted-foreground">Operations & Workflow</h3>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-card border rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <h4 class="text-base font-semibold mb-6">Job Card Status Distribution</h4>
                        <div class="flex flex-col md:flex-row items-center gap-8">
                            <div class="w-full md:w-1/2">
                                <div class="relative h-[240px] flex items-center justify-center">
                                    <canvas id="statusDistChart"></canvas>
                                </div>
                            </div>
                            <div class="w-full md:w-1/2 space-y-3 text-sm">
                                @foreach($statusDistribution as $s)
                                    <div class="flex justify-between items-center py-2 px-3 rounded-lg hover:bg-muted/50 transition-colors">
                                        <span class="capitalize text-muted-foreground font-medium">{{ str_replace('_', ' ', $s['label']) }}</span>
                                        <span class="font-bold text-base">{{ $s['count'] }}</span>
                                    </div>
                                @endforeach
                                <div class="pt-3 border-t flex justify-between items-center py-2 px-3 rounded-lg bg-primary/5">
                                    <span class="font-semibold">Total Open</span>
                                    <span class="font-bold text-lg text-primary">{{ $openJobCardsCount }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gradient-to-br from-violet-50 to-white dark:from-violet-950/20 dark:to-card border border-violet-200 dark:border-violet-900 rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow flex flex-col justify-center">
                        <p class="text-xs font-medium uppercase tracking-wide text-violet-700 dark:text-violet-400">Avg Turnaround Time</p>
                        <p class="text-5xl font-bold tracking-tight mt-3 text-violet-900 dark:text-violet-100">
                            {{ number_format($avgTurnaroundDays, 1) }}
                            <span class="text-xl font-medium text-muted-foreground">days</span>
                        </p>
                        <p class="text-xs text-muted-foreground mt-4">Job date → delivered (updated_at)</p>
                        <p class="text-xs text-violet-600 dark:text-violet-400 mt-1 font-medium">Delivered jobs only</p>
                    </div>
                </div>
            </div>

            {{-- Money Owed Section --}}
            <div class="space-y-6">
                <div class="flex items-center gap-2">
                    <div class="h-1 w-1 rounded-full bg-primary"></div>
                    <h3 class="text-sm font-semibold tracking-wider uppercase text-muted-foreground">Outstanding Balance</h3>
                </div>

                <div class="bg-card border rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                    <h4 class="text-base font-semibold mb-6">Aging Buckets</h4>
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                        <div class="space-y-3">
                            <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900 rounded-xl p-4 text-center">
                                <p class="text-xs font-medium text-emerald-700 dark:text-emerald-400 mb-1">0–7 days</p>
                                <p class="text-2xl font-bold text-emerald-900 dark:text-emerald-100">₹{{ number_format($agingBuckets['0-7'], 2) }}</p>
                            </div>
                            <div class="bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900 rounded-xl p-4 text-center">
                                <p class="text-xs font-medium text-amber-700 dark:text-amber-400 mb-1">8–30 days</p>
                                <p class="text-2xl font-bold text-amber-900 dark:text-amber-100">₹{{ number_format($agingBuckets['8-30'], 2) }}</p>
                            </div>
                            <div class="bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900 rounded-xl p-4 text-center">
                                <p class="text-xs font-medium text-rose-700 dark:text-rose-400 mb-1">30+ days</p>
                                <p class="text-2xl font-bold text-rose-900 dark:text-rose-100">₹{{ number_format($agingBuckets['30+'], 2) }}</p>
                            </div>
                        </div>
                        <div class="lg:col-span-3">
                            <div class="relative h-[280px]">
                                <canvas id="agingChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('customers.index') }}" class="mt-4 inline-flex items-center gap-2 text-sm text-primary hover:underline font-medium">
                        View customer balances
                        <x-heroicon-o-arrow-right class="w-4 h-4" />
                    </a>
                </div>
            </div>

            {{-- Customers Section --}}
            <div class="space-y-6">
                <div class="flex items-center gap-2">
                    <div class="h-1 w-1 rounded-full bg-primary"></div>
                    <h3 class="text-sm font-semibold tracking-wider uppercase text-muted-foreground">Customers</h3>
                </div>

                <div class="bg-card border rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                    <h4 class="text-base font-semibold mb-6">New vs Returning — Last 6 Months</h4>
                    <div class="relative h-[260px]">
                        <canvas id="newVsReturningChart"></canvas>
                    </div>
                </div>

                <div class="bg-card border rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                    <h4 class="text-base font-semibold mb-6">Top Customers by Spend — Last 30 Days</h4>
                    @if($topCustomersBySpend->isEmpty())
                        <p class="text-sm text-muted-foreground text-center py-8">No payments in range.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-xs text-muted-foreground uppercase border-b">
                                        <th class="text-left py-3 font-semibold">#</th>
                                        <th class="text-left py-3 font-semibold">Customer</th>
                                        <th class="text-right py-3 font-semibold">Visits</th>
                                        <th class="text-right py-3 font-semibold">Amount Paid</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach($topCustomersBySpend as $i => $row)
                                        <tr class="hover:bg-muted/50 transition-colors">
                                            <td class="py-3 text-muted-foreground">{{ $i + 1 }}</td>
                                            <td class="py-3 font-medium">
                                                @if($row['customer'])
                                                    <a href="{{ route('customers.show', $row['customer']) }}" class="hover:text-primary hover:underline">
                                                        {{ $row['customer']->display_name }}
                                                    </a>
                                                    <div class="text-xs text-muted-foreground">{{ $row['customer']->mobile }}</div>
                                                @else
                                                    <span class="text-muted-foreground">—</span>
                                                @endif
                                            </td>
                                            <td class="py-3 text-right font-medium">{{ $row['visits'] }}</td>
                                            <td class="py-3 text-right font-bold text-base">₹{{ number_format($row['total_paid'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Inventory Section (Fast/Slow Movers Only) --}}
            <div class="space-y-6">
                <div class="flex items-center gap-2">
                    <div class="h-1 w-1 rounded-full bg-primary"></div>
                    <h3 class="text-sm font-semibold tracking-wider uppercase text-muted-foreground">Parts Usage</h3>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-gradient-to-br from-emerald-50 to-white dark:from-emerald-950/20 dark:to-card border border-emerald-200 dark:border-emerald-900 rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <h4 class="text-base font-semibold mb-5 text-emerald-900 dark:text-emerald-100 flex items-center gap-2">
                            <x-heroicon-o-arrow-trending-up class="w-5 h-5" />
                            Fast Moving Parts — Last 30 Days
                        </h4>
                        @if($fastMoving->isEmpty())
                            <p class="text-sm text-muted-foreground text-center py-8">No usage data available.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($fastMoving as $index => $f)
                                    <div class="flex items-center justify-between p-3 rounded-lg bg-white dark:bg-card border border-emerald-100 dark:border-emerald-900 hover:shadow-sm transition-shadow">
                                        <div class="flex items-center gap-3">
                                            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-emerald-500 text-white text-xs font-bold">{{ $index + 1 }}</span>
                                            <div>
                                                <p class="font-medium text-sm">{{ $f['part']->part_name }}</p>
                                                <p class="text-xs text-muted-foreground">{{ $f['part']->part_code }}</p>
                                            </div>
                                        </div>
                                        <span class="font-bold text-lg text-emerald-700 dark:text-emerald-400">{{ $f['used'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <a href="{{ route('inventory.index') }}" class="mt-4 inline-flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-400 hover:underline font-medium">
                            View all parts
                            <x-heroicon-o-arrow-right class="w-4 h-4" />
                        </a>
                    </div>

                    <div class="bg-gradient-to-br from-slate-50 to-white dark:from-slate-950/20 dark:to-card border border-slate-200 dark:border-slate-900 rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <h4 class="text-base font-semibold mb-5 text-slate-900 dark:text-slate-100 flex items-center gap-2">
                            <x-heroicon-o-arrow-trending-down class="w-5 h-5" />
                            Slow Moving Parts — Last 30 Days
                        </h4>
                        @if($slowMoving->isEmpty())
                            <p class="text-sm text-muted-foreground text-center py-8">No slow movers.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($slowMoving as $index => $s)
                                    <div class="flex items-center justify-between p-3 rounded-lg bg-white dark:bg-card border border-slate-100 dark:border-slate-900 hover:shadow-sm transition-shadow">
                                        <div class="flex items-center gap-3">
                                            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-slate-400 text-white text-xs font-bold">{{ $index + 1 }}</span>
                                            <div>
                                                <p class="font-medium text-sm text-muted-foreground">{{ $s['part']->part_name }}</p>
                                                <p class="text-xs text-muted-foreground">{{ $s['part']->part_code }}</p>
                                            </div>
                                        </div>
                                        <span class="font-bold text-base text-muted-foreground">{{ $s['used'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Technicians Section --}}
            @if($technicianStats->isNotEmpty())
            <div class="space-y-6">
                <div class="flex items-center gap-2">
                    <div class="h-1 w-1 rounded-full bg-primary"></div>
                    <h3 class="text-sm font-semibold tracking-wider uppercase text-muted-foreground">Technicians Performance</h3>
                </div>

                <div class="bg-card border rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                    <h4 class="text-base font-semibold mb-6">Jobs Completed + Revenue — Delivered Only</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-muted-foreground uppercase border-b">
                                    <th class="text-left py-3 font-semibold">Technician</th>
                                    <th class="text-right py-3 font-semibold">Jobs Completed</th>
                                    <th class="text-right py-3 font-semibold">Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach($technicianStats as $t)
                                    <tr class="hover:bg-muted/50 transition-colors">
                                        <td class="py-3 font-medium">{{ $t['technician']?->name ?? 'Unassigned' }}</td>
                                        <td class="py-3 text-right font-medium">{{ $t['jobs'] }}</td>
                                        <td class="py-3 text-right font-bold text-base">₹{{ number_format($t['revenue'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- Vehicles Section --}}
            @if($revenueByVehicleBrand->isNotEmpty())
            <div class="space-y-6">
                <div class="flex items-center gap-2">
                    <div class="h-1 w-1 rounded-full bg-primary"></div>
                    <h3 class="text-sm font-semibold tracking-wider uppercase text-muted-foreground">Vehicle Brands</h3>
                </div>

                <div class="bg-card border rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                    <h4 class="text-base font-semibold mb-6">Revenue by Vehicle Brand — This Month</h4>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="relative h-[300px]">
                            <canvas id="revVehicleBrandChart"></canvas>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-xs text-muted-foreground uppercase border-b">
                                        <th class="text-left py-3 font-semibold">Brand</th>
                                        <th class="text-right py-3 font-semibold">Jobs</th>
                                        <th class="text-right py-3 font-semibold">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach($revenueByVehicleBrand as $row)
                                        <tr class="hover:bg-muted/50 transition-colors">
                                            <td class="py-3 font-medium">{{ $row['brand'] }}</td>
                                            <td class="py-3 text-right font-medium">{{ $row['count'] }}</td>
                                            <td class="py-3 text-right font-bold text-base">₹{{ number_format($row['total'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Chart === 'undefined') return;
            
            const cur = @json(config('garage.currency_symbol'));
            const chartDefaults = {
                animation: false,
                responsiveAnimationDuration: 0,
                transitions: { active: { animation: { duration: 0 } } }
            };

            // Revenue trend toggle
            const dailyLabels = @json($revenueDailyTrend->pluck('date'));
            const dailyData = @json($revenueDailyTrend->pluck('total'));
            const weeklyLabels = @json($revenueWeeklyTrend->pluck('date'));
            const weeklyData = @json($revenueWeeklyTrend->pluck('total'));
            const monthlyLabels = @json($revenueMonthlyTrend->pluck('label'));
            const monthlyData = @json($revenueMonthlyTrend->pluck('total'));

            const revTrendChart = new Chart(document.getElementById('revTrendChart'), {
                type: 'bar',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'Revenue',
                        data: monthlyData,
                        backgroundColor: '#6366f1',
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    ...chartDefaults,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: v => cur + v.toLocaleString('en-IN') },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });

            document.getElementById('revTrendRange')?.addEventListener('change', (e) => {
                const range = e.target.value;
                let labels, data;
                if (range === 'weekly') {
                    labels = weeklyLabels;
                    data = weeklyData;
                } else if (range === 'monthly') {
                    labels = monthlyLabels;
                    data = monthlyData;
                } else {
                    labels = dailyLabels;
                    data = dailyData;
                }
                revTrendChart.data.labels = labels;
                revTrendChart.data.datasets[0].data = data;
                revTrendChart.update();
            });

            // Revenue source donut
            new Chart(document.getElementById('revSourceChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Job Cards', 'Counter Invoices'],
                    datasets: [{
                        data: [@json($jobCardsRevenueMonth), @json($counterRevenueMonth)],
                        backgroundColor: ['#6366f1', '#16a34a'],
                        borderWidth: 0
                    }]
                },
                options: {
                    ...chartDefaults,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                font: { size: 12 }
                            }
                        }
                    }
                }
            });

            // Revenue by Job Type
            @if($revenueByJobType->isNotEmpty())
            new Chart(document.getElementById('revJobTypeChart'), {
                type: 'bar',
                data: {
                    labels: @json($revenueByJobType->pluck('job_type')->map(fn($v) => ucwords(str_replace('_', ' ', $v)))),
                    datasets: [{
                        data: @json($revenueByJobType->pluck('total')),
                        backgroundColor: '#6366f1',
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    ...chartDefaults,
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { callback: v => cur + v.toLocaleString('en-IN') } },
                        y: { grid: { display: false } }
                    }
                }
            });
            @endif

            // Average Job Value
            new Chart(document.getElementById('avgJobValueChart'), {
                type: 'line',
                data: {
                    labels: @json($avgJobValueTrend->pluck('label')),
                    datasets: [{
                        label: 'Avg Value',
                        data: @json($avgJobValueTrend->pluck('avg')),
                        borderColor: '#f97316',
                        backgroundColor: 'rgba(249,115,22,0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 3
                    }]
                },
                options: {
                    ...chartDefaults,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => cur + v.toLocaleString('en-IN') } }
                    }
                }
            });

            // Payment Modes
            @if($paymentModeBreakdown->isNotEmpty())
            new Chart(document.getElementById('payModeChart'), {
                type: 'doughnut',
                data: {
                    labels: @json($paymentModeBreakdown->keys()->map(fn($k) => ucwords(str_replace('_', ' ', $k)))),
                    datasets: [{
                        data: @json($paymentModeBreakdown->pluck('total')->values()),
                        backgroundColor: ['#22c55e', '#3b82f6', '#f59e0b', '#06b6d4', '#ef4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    ...chartDefaults,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 12,
                                font: { size: 11 }
                            }
                        }
                    }
                }
            });
            @endif

            // Job Card Status Distribution
            new Chart(document.getElementById('statusDistChart'), {
                type: 'doughnut',
                data: {
                    labels: @json($statusDistribution->pluck('label')->map(fn($l) => ucwords(str_replace('_', ' ', $l)))),
                    datasets: [{
                        data: @json($statusDistribution->pluck('count')),
                        backgroundColor: ['#9ca3af', '#a78bfa', '#fbbf24', '#60a5fa', '#34d399', '#f87171'],
                        borderWidth: 0
                    }]
                },
                options: {
                    ...chartDefaults,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 10,
                                font: { size: 10 }
                            }
                        }
                    }
                }
            });

            // Aging Buckets
            new Chart(document.getElementById('agingChart'), {
                type: 'bar',
                data: {
                    labels: ['0–7 days', '8–30 days', '30+ days'],
                    datasets: [{
                        data: [@json($agingBuckets['0-7']), @json($agingBuckets['8-30']), @json($agingBuckets['30+'])],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    ...chartDefaults,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => cur + v.toLocaleString('en-IN') } },
                        x: { grid: { display: false } }
                    }
                }
            });

            // New vs Returning Customers
            new Chart(document.getElementById('newVsReturningChart'), {
                type: 'line',
                data: {
                    labels: @json($newVsReturning->pluck('label')),
                    datasets: [
                        {
                            label: 'New',
                            data: @json($newVsReturning->pluck('new')),
                            borderColor: '#22c55e',
                            backgroundColor: 'rgba(34,197,94,0.1)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            borderWidth: 3
                        },
                        {
                            label: 'Returning',
                            data: @json($newVsReturning->pluck('returning')),
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59,130,246,0.1)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            borderWidth: 3
                        }
                    ]
                },
                options: {
                    ...chartDefaults,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { usePointStyle: true, padding: 15 }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });

            // Revenue by Vehicle Brand
            @if($revenueByVehicleBrand->isNotEmpty())
            new Chart(document.getElementById('revVehicleBrandChart'), {
                type: 'bar',
                data: {
                    labels: @json($revenueByVehicleBrand->pluck('brand')),
                    datasets: [{
                        data: @json($revenueByVehicleBrand->pluck('total')),
                        backgroundColor: '#6366f1',
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    ...chartDefaults,
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { callback: v => cur + v.toLocaleString('en-IN') } },
                        y: { grid: { display: false } }
                    }
                }
            });
            @endif
        });
    </script>
</x-app-layout>
