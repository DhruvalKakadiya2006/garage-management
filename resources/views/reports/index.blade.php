<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-chart-bar class="w-6 h-6 text-primary-600" />
            Reports
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="bg-primary-50 border border-primary-200 text-primary-800 px-6 py-4 rounded-md mb-6">
                <h3 class="font-semibold mb-2 flex items-center gap-2">
                    <x-heroicon-o-chart-bar class="w-5 h-5" />
                    Reports Overview
                </h3>
                <p class="text-sm">
                    All reports support date-range filtering and generate bookmarkable URLs for easy sharing. 
                    Select a report below to view detailed analytics.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                
                <!-- Revenue Report Card -->
                <a href="{{ route('reports.revenue') }}" 
                   class="block bg-white shadow-sm hover:shadow-md transition-shadow sm:rounded-lg p-6 border-l-4 border-green-500">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Revenue Report</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Total revenue split by source (delivered job cards and counter invoices) with a daily trend chart.
                            </p>
                            <ul class="text-xs text-gray-500 space-y-1">
                                <li>• Job Cards revenue (delivered only)</li>
                                <li>• Counter Invoice revenue</li>
                                <li>• Revenue by job type</li>
                                <li>• Daily revenue trend</li>
                            </ul>
                        </div>
                        <div class="ml-4 text-green-500">
                            <x-heroicon-o-banknotes class="w-10 h-10" />
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <span class="inline-flex items-center text-sm text-primary-600 hover:text-primary-800 font-medium">
                            View Report
                            <x-heroicon-o-arrow-right class="w-4 h-4 ml-1" />
                        </span>
                    </div>
                </a>

                <!-- Job Cards Report Card -->
                <a href="{{ route('reports.job_cards') }}" 
                   class="block bg-white shadow-sm hover:shadow-md transition-shadow sm:rounded-lg p-6 border-l-4 border-primary-500">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Job Cards Report</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Job card counts summarized by status and job type within the selected range.
                            </p>
                            <ul class="text-xs text-gray-500 space-y-1">
                                <li>• Total job cards in range</li>
                                <li>• Job cards by status</li>
                                <li>• Job type distribution with percentages</li>
                            </ul>
                        </div>
                        <div class="ml-4 text-primary-500">
                            <x-heroicon-o-wrench-screwdriver class="w-10 h-10" />
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <span class="inline-flex items-center text-sm text-primary-600 hover:text-primary-800 font-medium">
                            View Report
                            <x-heroicon-o-arrow-right class="w-4 h-4 ml-1" />
                        </span>
                    </div>
                </a>

                <!-- Inventory Report Card -->
                <a href="{{ route('reports.inventory') }}" 
                   class="block bg-white shadow-sm hover:shadow-md transition-shadow sm:rounded-lg p-6 border-l-4 border-amber-500">
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
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <span class="inline-flex items-center text-sm text-primary-600 hover:text-primary-800 font-medium">
                            View Report
                            <x-heroicon-o-arrow-right class="w-4 h-4 ml-1" />
                        </span>
                    </div>
                </a>

                <!-- Payments Report Card -->
                <a href="{{ route('reports.payments') }}" 
                   class="block bg-white shadow-sm hover:shadow-md transition-shadow sm:rounded-lg p-6 border-l-4 border-purple-500">
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
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <span class="inline-flex items-center text-sm text-primary-600 hover:text-primary-800 font-medium">
                            View Report
                            <x-heroicon-o-arrow-right class="w-4 h-4 ml-1" />
                        </span>
                    </div>
                </a>

                <!-- Customers Report Card -->
                <a href="{{ route('reports.customers') }}" 
                   class="block bg-white shadow-sm hover:shadow-md transition-shadow sm:rounded-lg p-6 border-l-4 border-pink-500">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Customers Report</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Customers ranked by amount paid, with visit counts within the selected range.
                            </p>
                            <ul class="text-xs text-gray-500 space-y-1">
                                <li>• Top customers by amount paid</li>
                                <li>• Visit count per customer</li>
                            </ul>
                        </div>
                        <div class="ml-4 text-pink-500">
                            <x-heroicon-o-user-group class="w-10 h-10" />
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <span class="inline-flex items-center text-sm text-primary-600 hover:text-primary-800 font-medium">
                            View Report
                            <x-heroicon-o-arrow-right class="w-4 h-4 ml-1" />
                        </span>
                    </div>
                </a>

                <!-- Placeholder for future reports -->
                <div class="bg-gray-50 shadow-sm sm:rounded-lg p-6 border-l-4 border-gray-300 flex items-center justify-center">
                    <div class="text-center text-gray-400">
                        <div class="mb-2 flex justify-center text-gray-300">
                            <x-heroicon-o-plus class="w-10 h-10" />
                        </div>
                        <p class="text-sm font-medium">More reports coming soon</p>
                    </div>
                </div>

            </div>

            <div class="mt-8 bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <x-heroicon-o-document-text class="w-5 h-5 text-gray-500" />
                    Report Features
                </h3>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li class="flex items-start">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 mr-2 shrink-0" />
                        <span><strong>Date Range Filtering:</strong> All reports support from/to date filtering with defaults to the current month.</span>
                    </li>
                    <li class="flex items-start">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 mr-2 shrink-0" />
                        <span><strong>Bookmarkable URLs:</strong> Filter parameters are in the URL, making reports easy to share and bookmark.</span>
                    </li>
                    <li class="flex items-start">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 mr-2 shrink-0" />
                        <span><strong>Read-Only:</strong> Reports are view-only — no accidental modifications to your data.</span>
                    </li>
                    <li class="flex items-start">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 mr-2 shrink-0" />
                        <span><strong>Real-Time Data:</strong> All reports pull fresh data from your database on each view.</span>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</x-app-layout>
