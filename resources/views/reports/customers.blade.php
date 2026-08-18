<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-user-group class="w-6 h-6 text-primary-600" />
                Customers Report
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
                :action="route('reports.customers')" 
            />

            <div class="bg-white shadow-sm rounded-xl p-6">
                <h3 class="font-semibold text-gray-900 mb-1">Customers Ranked by Amount Paid</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Based on Job Cards and Counter Invoices created between 
                    <strong>{{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }}</strong> and 
                    <strong>{{ \Carbon\Carbon::parse($toDate)->format('d M Y') }}</strong>.
                    Amount paid is the total of all payments received against those documents;
                    visit count is the number of documents raised for the customer in the range.
                </p>

                @if($customers->isEmpty())
                    <p class="text-sm text-gray-500">No customer activity found in this date range.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Visits</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount Paid</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($customers as $index => $row)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ $index + 1 }}</td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900">{{ $row['customer']->display_name }}</div>
                                        <div class="text-xs text-gray-500">{{ $row['customer']->mobile ?? '-' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600 capitalize">
                                        {{ str_replace('-', ' ', $row['customer']->customer_type) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-gray-900 font-medium">
                                        {{ $row['visits'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-gray-900 font-medium">
                                        {{ config('garage.currency_symbol') }}{{ number_format($row['total_paid'], 2) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
