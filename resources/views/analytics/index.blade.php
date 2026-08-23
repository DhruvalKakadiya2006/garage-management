<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-chart-bar class="w-6 h-6 text-primary" />
            Analytics
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- KPI Snapshot Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white shadow-sm rounded-xl p-6 border-l-4 border-red-500">
                    <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Total Outstanding</p>
                    <p class="text-2xl font-bold text-red-600">₹{{ number_format($totalOutstanding, 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Across job cards & counter invoices</p>
                </div>

                <div class="bg-white shadow-sm rounded-xl p-6 border-l-4 border-primary">
                    <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Open Job Cards</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $openJobCardsCount }}</p>
                    <p class="text-xs text-gray-500 mt-1">Not yet delivered or cancelled</p>
                </div>

                <div class="bg-white shadow-sm rounded-xl p-6 border-l-4 border-green-500">
                    <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Revenue This Month</p>
                    <p class="text-2xl font-bold text-gray-900">₹{{ number_format($revenueThisMonth, 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Delivered job cards + counter invoices</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                <!-- Revenue Report Card -->
                <a href="{{ route('analytics.revenue') }}" 
                   class="block bg-white shadow-sm hover:shadow-md transition-shadow rounded-xl p-6 border-l-4 border-green-500">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Revenue Report</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Total revenue split by source (delivered job cards and counter invoices) with a daily trend chart and 6-month trajectory line chart.
                            </p>
                            <ul class="text-xs text-gray-500 space-y-1">
                                <li>• Job Cards revenue (delivered only)</li>
                                <li>• Counter Invoice revenue</li>
                                <li>• Revenue by job type</li>
                                <li>• Daily revenue trend</li>
                                <li>• 6-month revenue trend</li>
                            </ul>
                        </div>
                        <div class="ml-4 text-green-500">
                            <x-heroicon-o-banknotes class="w-10 h-10" />
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-border">
                        <span class="inline-flex items-center gap-1 text-sm text-primary hover:text-primary-700 font-medium">
                            View Report
                            <x-heroicon-o-arrow-right class="w-4 h-4" />
                        </span>
                    </div>
                </a>

                <!-- Job Cards Report Card -->
                <a href="{{ route('analytics.job_cards') }}" 
                   class="block bg-white shadow-sm hover:shadow-md transition-shadow rounded-xl p-6 border-l-4 border-primary">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Job Cards Report</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Job card counts summarized by status and job type within the selected range, plus average job value and status doughnut chart.
                            </p>
                            <ul class="text-xs text-gray-500 space-y-1">
                                <li>• Total job cards in range</li>
                                <li>• Job cards by status</li>
                                <li>• Job type distribution with percentages</li>
                                <li>• Average job value of delivered cards</li>
                                <li>• Status doughnut chart</li>
                            </ul>
                        </div>
                        <div class="ml-4 text-primary">
                            <x-heroicon-o-wrench-screwdriver class="w-10 h-10" />
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-border">
                        <span class="inline-flex items-center gap-1 text-sm text-primary hover:text-primary-700 font-medium">
                            View Report
                            <x-heroicon-o-arrow-right class="w-4 h-4" />
                        </span>
                    </div>
                </a>

                <!-- Inventory Report Card -->
                <a href="{{ route('analytics.inventory') }}" 
                   class="block bg-white shadow-sm hover:shadow-md transition-shadow rounded-xl p-6 border-l-4 border-amber-500">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Inventory Report</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Current stock levels, low stock alerts, and parts usage within the selected range.
                            </p>
                            <ul class="text-xs text-gray-500 space-y-1">
                                <li>• Current stock levels</li>
                                <li>• Low stock alerts (at/below reorder level)</li>
                                <li>• Parts usage — fast vs slow movers</li>
                                <li>• Stock valuation at cost</li>
                            </ul>
                        </div>
                        <div class="ml-4 text-amber-500">
                            <x-heroicon-o-cube class="w-10 h-10" />
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-border">
                        <span class="inline-flex items-center gap-1 text-sm text-primary hover:text-primary-700 font-medium">
                            View Report
                            <x-heroicon-o-arrow-right class="w-4 h-4" />
                        </span>
                    </div>
                </a>

                <!-- Payments Report Card -->
                <a href="{{ route('analytics.payments') }}" 
                   class="block bg-white shadow-sm hover:shadow-md transition-shadow rounded-xl p-6 border-l-4 border-purple-500">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Payments Report</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Total payments collected, broken down by mode, with a full list of individual payments.
                            </p>
                            <ul class="text-xs text-gray-500 space-y-1">
                                <li>• Total payments collected</li>
                                <li>• Breakdown by payment mode</li>
                                <li>• Individual payment list</li>
                            </ul>
                        </div>
                        <div class="ml-4 text-purple-500">
                            <x-heroicon-o-credit-card class="w-10 h-10" />
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-border">
                        <span class="inline-flex items-center gap-1 text-sm text-primary hover:text-primary-700 font-medium">
                            View Report
                            <x-heroicon-o-arrow-right class="w-4 h-4" />
                        </span>
                    </div>
                </a>

                <!-- Customers Report Card -->
                <a href="{{ route('analytics.customers') }}" 
                   class="block bg-white shadow-sm hover:shadow-md transition-shadow rounded-xl p-6 border-l-4 border-pink-500">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Customers Report</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Customers ranked by amount paid, with visit counts within the selected range, and revenue split by customer type.
                            </p>
                            <ul class="text-xs text-gray-500 space-y-1">
                                <li>• Top customers by amount paid</li>
                                <li>• Visit count per customer</li>
                                <li>• Revenue by customer type (Regular / Counter / Walk-in)</li>
                            </ul>
                        </div>
                        <div class="ml-4 text-pink-500">
                            <x-heroicon-o-user-group class="w-10 h-10" />
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-border">
                        <span class="inline-flex items-center gap-1 text-sm text-primary hover:text-primary-700 font-medium">
                            View Report
                            <x-heroicon-o-arrow-right class="w-4 h-4" />
                        </span>
                    </div>
                </a>

                <!-- Placeholder -->
                <div class="bg-muted shadow-sm rounded-xl p-6 border-l-4 border-flex flex-col items-center justify-center">
                    <div class="text-center text-gray-400">
                        <div class="mb-2 flex justify-center text-gray-300">
                            <x-heroicon-o-plus class="w-10 h-10" />
                        </div>
                        <p class="text-sm font-medium">More analytics coming soon</p>
                    </div>
                </div>

            </div>

            <div class="mt-8 bg-white shadow-sm rounded-xl p-6">
                <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <x-heroicon-o-document-text class="w-5 h-5 text-gray-500" />
                    Analytics Features
                </h3>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li class="flex items-start gap-2">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0" />
                        <span><strong>Date Range Filtering:</strong> All reports support from/to date filtering with defaults to the current month.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0" />
                        <span><strong>Bookmarkable URLs:</strong> Filter parameters are in the URL, making reports easy to share and bookmark.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0" />
                        <span><strong>Read-Only:</strong> Analytics are view-only — no accidental modifications to your data.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0" />
                        <span><strong>Fresh Data:</strong> All analytics pull fresh data from your database on each view.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0" />
                        <span><strong>Snapshot KPIs:</strong> Top-level figures (outstanding, open jobs, revenue this month) on the landing page.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0" />
                        <span><strong>6-Month Trend:</strong> Revenue trajectory chart independent of selected date range.</span>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</x-app-layout>