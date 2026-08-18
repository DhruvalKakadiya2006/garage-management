<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-wrench-screwdriver class="w-6 h-6 text-primary-600" />
                Job Cards Report
            </h2>
            <a href="{{ route('reports.index') }}" 
               class="inline-flex items-center text-sm text-gray-600 hover:text-gray-800">
                <x-heroicon-o-arrow-left class="w-4 h-4 mr-1" />
                Back to Reports
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Date Range Filter -->
            <x-date-range-filter 
                :fromDate="$fromDate" 
                :toDate="$toDate" 
                :action="route('reports.job_cards')" 
            />

            <!-- Summary -->
            <div class="bg-gradient-to-r from-primary-500 to-primary-600 shadow-lg rounded-xl p-8 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-primary-100 text-sm uppercase tracking-wide mb-2">Job Cards Created</p>
                        <p class="text-4xl font-bold">{{ $totalJobCards }}</p>
                        <p class="text-primary-100 text-sm mt-2">
                            {{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }} 
                            to 
                            {{ \Carbon\Carbon::parse($toDate)->format('d M Y') }}
                        </p>
                    </div>
                    <div class="opacity-50">
                        <x-heroicon-o-wrench-screwdriver class="w-16 h-16" />
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
                                <tr class="bg-gray-50 font-semibold">
                                    <td class="px-6 py-4">Total</td>
                                    <td class="px-6 py-4 text-right">{{ $totalJobCards }}</td>
                                    <td class="px-6 py-4 text-right">100%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif
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
                                <tr class="bg-gray-50 font-semibold">
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
