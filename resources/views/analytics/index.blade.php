<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl tracking-tight">
            <x-heroicon-o-chart-bar class="w-6 h-6 text-primary" />
            Analytics
        </h2>
    </x-slot>

    <div class="py-6 bg-muted/20 min-h-screen">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Revenue & Sales --}}
            <div>
                <h3 class="text-sm font-semibold tracking-widest uppercase text-muted-foreground mb-3">Revenue & Sales</h3>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-card border rounded-xl p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-medium">Revenue Trend</h4>
                            <select id="revTrendRange" class="text-xs border border-input bg-background rounded-md px-2 py-1 focus:outline-none focus:ring-1 focus:ring-ring">
                                <option value="monthly" selected>Monthly (6m)</option>
                                <option value="weekly">Weekly (12w)</option>
                                <option value="daily">Daily (30d)</option>
                            </select>
                        </div>
                        <div class="relative h-[280px]"><canvas id="revTrendChart"></canvas></div>
                        <p class="text-xs text-muted-foreground mt-2">Per-period total (not cumulative) · Job Cards + Counter Invoices</p>
                    </div>
                    <div class="bg-card border rounded-xl p-6 shadow-sm">
                        <h4 class="text-sm font-medium mb-4">Revenue Split — This Month</h4>
                        <div class="relative h-[220px]"><canvas id="revSourceChart"></canvas></div>
                        <div class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between"><span class="text-muted-foreground">Job Cards (service)</span><span class="font-medium">₹{{ number_format($jobCardsRevenueMonth,2) }}</span></div>
                            <div class="flex justify-between"><span class="text-muted-foreground">Counter Invoices (parts)</span><span class="font-medium">₹{{ number_format($counterRevenueMonth,2) }}</span></div>
                            <div class="flex justify-between border-t pt-2 font-semibold"><span>Total</span><span>₹{{ number_format($revenueThisMonth,2) }}</span></div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
                    <div class="bg-card border rounded-xl p-6 shadow-sm">
                        <h4 class="text-sm font-medium mb-4">Revenue by Job Type</h4>
                        @if($revenueByJobType->isEmpty())
                            <p class="text-sm text-muted-foreground">No delivered jobs this month.</p>
                        @else
                            <div class="relative h-[220px]"><canvas id="revJobTypeChart"></canvas></div>
                        @endif
                    </div>
                    <div class="bg-card border rounded-xl p-6 shadow-sm">
                        <h4 class="text-sm font-medium mb-4">Average Job Value Trend</h4>
                        <div class="relative h-[220px]"><canvas id="avgJobValueChart"></canvas></div>
                        <p class="text-xs text-muted-foreground mt-3">Delivered jobs only · last 6 months</p>
                    </div>
                    <div class="bg-card border rounded-xl p-6 shadow-sm">
                        <h4 class="text-sm font-medium mb-4">Payment Mode Breakdown</h4>
                        @if($paymentModeBreakdown->isEmpty())
                            <p class="text-sm text-muted-foreground">No payments this month.</p>
                        @else
                            <div class="relative h-[220px]"><canvas id="payModeChart"></canvas></div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Operations / Workflow --}}
            <div>
                <h3 class="text-sm font-semibold tracking-widest uppercase text-muted-foreground mb-3">Operations / Workflow</h3>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-card border rounded-xl p-6 shadow-sm">
                        <h4 class="text-sm font-medium mb-4">Job Card Status Distribution — Right Now</h4>
                        <div class="flex items-center gap-6">
                            <div class="w-1/2"><div class="relative h-[220px]"><canvas id="statusDistChart"></canvas></div></div>
                            <div class="w-1/2 space-y-2 text-sm">
                                @foreach($statusDistribution as $s)
                                    <div class="flex justify-between"><span class="capitalize text-muted-foreground">{{ str_replace('_',' ',$s['label']) }}</span><span class="font-medium">{{ $s['count'] }}</span></div>
                                @endforeach
                                <div class="pt-2 border-t flex justify-between font-semibold"><span>Open</span><span>{{ $openJobCardsCount }}</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-card border rounded-xl p-6 shadow-sm flex flex-col justify-center">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Avg Turnaround Time</p>
                        <p class="text-4xl font-bold tracking-tight mt-2">{{ number_format($avgTurnaroundDays,1) }} <span class="text-lg font-medium text-muted-foreground">days</span></p>
                        <p class="text-xs text-muted-foreground mt-2">job_date → delivered (updated_at) · delivered jobs only</p>
                        <p class="text-xs text-muted-foreground mt-1">A real efficiency number most garage software hides.</p>
                    </div>
                </div>
            </div>

            {{-- Money Owed --}}
            <div>
                <h3 class="text-sm font-semibold tracking-widest uppercase text-muted-foreground mb-3">Money Owed</h3>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-card border rounded-xl p-6 shadow-sm">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Total Outstanding</p>
                        <p class="text-3xl font-bold text-destructive mt-1">₹{{ number_format($totalOutstanding,2) }}</p>
                        <p class="text-xs text-muted-foreground mt-1">Clamped per-document</p>
                        <a href="{{ route('customers.index') }}" class="text-xs text-primary hover:underline mt-3 inline-flex items-center gap-1">View customers <x-heroicon-o-arrow-right class="w-3 h-3"/></a>
                    </div>
                    <div class="lg:col-span-2 bg-card border rounded-xl p-6 shadow-sm">
                        <h4 class="text-sm font-medium mb-4">Aging Buckets</h4>
                        <div class="relative h-[220px]"><canvas id="agingChart"></canvas></div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                            <div class="rounded-lg bg-muted p-3 text-center"><div class="text-muted-foreground">0–7 days</div><div class="font-semibold">{{ config('garage.currency_symbol') }}{{ number_format($agingBuckets['0-7'],2) }}</div></div>
                            <div class="rounded-lg bg-muted p-3 text-center"><div class="text-muted-foreground">8–30 days</div><div class="font-semibold">{{ config('garage.currency_symbol') }}{{ number_format($agingBuckets['8-30'],2) }}</div></div>
                            <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-center"><div class="text-amber-700">30+ days</div><div class="font-semibold text-amber-700">{{ config('garage.currency_symbol') }}{{ number_format($agingBuckets['30+'],2) }}</div></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Customers --}}
            <div>
                <h3 class="text-sm font-semibold tracking-widest uppercase text-muted-foreground mb-3">Customers</h3>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-3 bg-card border rounded-xl p-6 shadow-sm">
                        <h4 class="text-sm font-medium mb-4">New vs Returning — Last 6 Months</h4>
                        <div class="relative h-[240px]"><canvas id="newVsReturningChart"></canvas></div>
                    </div>
                </div>
                <div class="bg-card border rounded-xl p-6 shadow-sm mt-6">
                    <h4 class="text-sm font-medium mb-4">Top Customers by Spend — Last 30 Days</h4>
                    @if($topCustomersBySpend->isEmpty())
                        <p class="text-sm text-muted-foreground">No payments in range.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead><tr class="text-xs text-muted-foreground uppercase border-b"><th class="text-left py-2 font-medium">#</th><th class="text-left py-2 font-medium">Customer</th><th class="text-right py-2 font-medium">Visits</th><th class="text-right py-2 font-medium">Paid</th></tr></thead>
                                <tbody class="divide-y">
                                    @foreach($topCustomersBySpend as $i => $row)
                                        <tr>
                                            <td class="py-2 text-muted-foreground">{{ $i+1 }}</td>
                                            <td class="py-2 font-medium"><a href="{{ $row['customer'] ? route('customers.show',$row['customer']) : '#' }}" class="hover:underline">{{ $row['customer']?->display_name ?? '—' }}</a><div class="text-xs text-muted-foreground">{{ $row['customer']?->mobile ?? '' }}</div></td>
                                            <td class="py-2 text-right">{{ $row['visits'] }}</td>
                                            <td class="py-2 text-right font-medium">₹{{ number_format($row['total_paid'],2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Technicians --}}
            <div>
                <h3 class="text-sm font-semibold tracking-widest uppercase text-muted-foreground mb-3">Technicians</h3>
                <div class="bg-card border rounded-xl p-6 shadow-sm">
                    <h4 class="text-sm font-medium mb-4">Jobs Completed + Revenue per Technician — Delivered Only</h4>
                    @if($technicianStats->isEmpty())
                        <p class="text-sm text-muted-foreground">No delivered jobs with technician assigned.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead><tr class="text-xs text-muted-foreground uppercase border-b"><th class="text-left py-2 font-medium">Technician</th><th class="text-right py-2 font-medium">Jobs</th><th class="text-right py-2 font-medium">Revenue</th></tr></thead>
                                <tbody class="divide-y">
                                    @foreach($technicianStats as $t)
                                        <tr>
                                            <td class="py-2 font-medium">{{ $t['technician']?->name ?? 'Unassigned' }}</td>
                                            <td class="py-2 text-right">{{ $t['jobs'] }}</td>
                                            <td class="py-2 text-right font-medium">₹{{ number_format($t['revenue'],2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Inventory --}}
            <div>
                <h3 class="text-sm font-semibold tracking-widest uppercase text-muted-foreground mb-3">Inventory</h3>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-card border rounded-xl p-6 shadow-sm">
                        <h4 class="text-sm font-medium mb-3">Low-Stock Alerts <span class="ml-2 inline-flex items-center rounded-full bg-destructive text-destructive-foreground px-2 py-0.5 text-xs">{{ $lowStockCount }}</span></h4>
                        @if($lowStockParts->isEmpty())
                            <p class="text-sm text-muted-foreground">All parts above reorder level.</p>
                        @else
                            <ul class="space-y-2">
                                @foreach($lowStockParts as $p)
                                    <li class="flex justify-between text-sm border rounded-lg px-3 py-2 {{ $p->current_stock_qty <= 0 ? 'bg-destructive/5 border-destructive/20' : 'bg-amber-50 border-amber-200' }}">
                                        <span class="font-medium truncate">{{ $p->part_name }} <span class="text-xs text-muted-foreground">{{ $p->part_code }}</span></span>
                                        <span class="font-semibold {{ $p->current_stock_qty <= 0 ? 'text-destructive' : 'text-amber-700' }}">{{ $p->current_stock_qty }} / {{ $p->reorder_level }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <a href="{{ route('inventory.index') }}" class="text-xs text-primary hover:underline mt-3 inline-flex items-center gap-1">Manage inventory <x-heroicon-o-arrow-right class="w-3 h-3"/></a>
                    </div>
                    <div class="bg-card border rounded-xl p-6 shadow-sm">
                        <h4 class="text-sm font-medium mb-3">Fast vs Slow-Moving — Last 30 Days (units used)</h4>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <p class="text-xs font-medium text-emerald-700 mb-2">Fastest 5</p>
                                @if($fastMoving->isEmpty())
                                    <p class="text-xs text-muted-foreground">No usage.</p>
                                @else
                                    <ul class="space-y-1.5 text-sm">
                                        @foreach($fastMoving as $f)
                                            <li class="flex justify-between"><span class="truncate">{{ $f['part']->part_name }}</span><span class="font-medium">{{ $f['used'] }}</span></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs font-medium text-muted-foreground mb-2">Slowest 5 (used &gt;0)</p>
                                @if($slowMoving->isEmpty())
                                    <p class="text-xs text-muted-foreground">No slow movers.</p>
                                @else
                                    <ul class="space-y-1.5 text-sm text-muted-foreground">
                                        @foreach($slowMoving as $s)
                                            <li class="flex justify-between"><span class="truncate">{{ $s['part']->part_name }}</span><span>{{ $s['used'] }}</span></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Vehicles — EV-brand-specific insight --}}
            <div>
                <h3 class="text-sm font-semibold tracking-widest uppercase text-muted-foreground mb-3">Vehicles</h3>
                <div class="bg-card border rounded-xl p-6 shadow-sm">
                    <h4 class="text-sm font-medium mb-4">Revenue by Vehicle Brand — This Month</h4>
                    @if($revenueByVehicleBrand->isEmpty())
                        <p class="text-sm text-muted-foreground">No delivered jobs this month.</p>
                    @else
                        <div class="relative h-[280px]"><canvas id="revVehicleBrandChart"></canvas></div>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead><tr class="text-xs text-muted-foreground uppercase border-b"><th class="text-left py-2 font-medium">Brand</th><th class="text-right py-2 font-medium">Jobs</th><th class="text-right py-2 font-medium">Revenue</th></tr></thead>
                                <tbody class="divide-y">
                                    @foreach($revenueByVehicleBrand as $row)
                                        <tr>
                                            <td class="py-2 font-medium">{{ $row['brand'] }}</td>
                                            <td class="py-2 text-right">{{ $row['count'] }}</td>
                                            <td class="py-2 text-right font-medium">₹{{ number_format($row['total'],2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Chart === 'undefined') return;
            const cur = @json(config('garage.currency_symbol'));
            const noAnim = { animation: false, responsiveAnimationDuration: 0, transitions: { active: { animation: { duration: 0 } } } };

            // Revenue trend — per-period (not cumulative) with daily/weekly/monthly toggle
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
                    datasets: [{ label: 'Revenue', data: monthlyData, backgroundColor: '#6366f1', borderColor: '#6366f1', borderWidth: 1, borderRadius: 4, borderSkipped: false }]
                },
                options: { ...noAnim, responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => cur+v.toLocaleString('en-IN') } }, x: { grid: { display: false } } } }
            });
            document.getElementById('revTrendRange')?.addEventListener('change', (e) => {
                const v = e.target.value;
                let labels, data;
                if (v === 'weekly') { labels = weeklyLabels; data = weeklyData; }
                else if (v === 'monthly') { labels = monthlyLabels; data = monthlyData; }
                else { labels = dailyLabels; data = dailyData; }
                revTrendChart.data.labels = labels;
                revTrendChart.data.datasets[0].data = data;
                revTrendChart.update();
            });

            // Revenue source donut
            new Chart(document.getElementById('revSourceChart'), {
                type: 'doughnut',
                data: { labels: ['Job Cards','Counter Invoices'], datasets: [{ data: [@json($jobCardsRevenueMonth), @json($counterRevenueMonth)], backgroundColor: ['#6366f1','#16a34a'] }] },
                options: { ...noAnim, responsive: true, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } } }
            });

            // Job type
            @if($revenueByJobType->isNotEmpty())
            new Chart(document.getElementById('revJobTypeChart'), {
                type: 'bar',
                data: { labels: @json($revenueByJobType->pluck('job_type')->map(fn($v)=>str_replace('_',' ',$v))), datasets: [{ data: @json($revenueByJobType->pluck('total')), backgroundColor: 'rgba(99,102,241,0.85)' }] },
                options: { ...noAnim, indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
            });
            @endif

            // Avg job value
            new Chart(document.getElementById('avgJobValueChart'), {
                type: 'line',
                data: { labels: @json($avgJobValueTrend->pluck('label')), datasets: [{ label: 'Avg ₹', data: @json($avgJobValueTrend->pluck('avg')), borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,0.08)', tension: 0.25, fill: true, pointRadius: 0, borderWidth: 2 }] },
                options: { ...noAnim, responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });

            // Payment modes
            @if($paymentModeBreakdown->isNotEmpty())
            new Chart(document.getElementById('payModeChart'), {
                type: 'doughnut',
                data: { labels: @json($paymentModeBreakdown->keys()->map(fn($k)=>ucwords(str_replace('_',' ',$k)))) , datasets: [{ data: @json($paymentModeBreakdown->pluck('total')->values()), backgroundColor: ['#22c55e','#3b82f6','#f59e0b','#06b6d4','#ef4444'] }] },
                options: { ...noAnim, responsive: true, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } } }
            });
            @endif

            // Status distribution
            new Chart(document.getElementById('statusDistChart'), {
                type: 'doughnut',
                data: { labels: @json($statusDistribution->pluck('label')), datasets: [{ data: @json($statusDistribution->pluck('count')), backgroundColor: ['#9ca3af','#a78bfa','#fbbf24','#60a5fa','#34d399','#f87171'] }] },
                options: { ...noAnim, responsive: true, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { size: 10 } } } } }
            });

            // Aging
            new Chart(document.getElementById('agingChart'), {
                type: 'bar',
                data: { labels: ['0–7 days','8–30 days','30+ days'], datasets: [{ data: [@json($agingBuckets['0-7']), @json($agingBuckets['8-30']), @json($agingBuckets['30+'])], backgroundColor: ['#22c55e','#f59e0b','#ef4444'] }] },
                options: { ...noAnim, responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => cur+v.toLocaleString('en-IN') } } } }
            });

            // New vs Returning
            new Chart(document.getElementById('newVsReturningChart'), {
                type: 'line',
                data: {
                    labels: @json($newVsReturning->pluck('label')),
                    datasets: [
                        { label: 'New', data: @json($newVsReturning->pluck('new')), borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.08)', tension: 0.25, fill: false, pointRadius: 0, borderWidth: 2 },
                        { label: 'Returning', data: @json($newVsReturning->pluck('returning')), borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.08)', tension: 0.25, fill: false, pointRadius: 0, borderWidth: 2 }
                    ]
                },
                options: { ...noAnim, responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });

            // Revenue by Vehicle Brand — horizontal bar, same style as Revenue by Job Type
            @if($revenueByVehicleBrand->isNotEmpty())
            new Chart(document.getElementById('revVehicleBrandChart'), {
                type: 'bar',
                data: { labels: @json($revenueByVehicleBrand->pluck('brand')), datasets: [{ data: @json($revenueByVehicleBrand->pluck('total')), backgroundColor: 'rgba(99,102,241,0.85)' }] },
                options: { ...noAnim, indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { callback: v => cur+v.toLocaleString('en-IN') } } } }
            });
            @endif
        });
    </script>
</x-app-layout>
