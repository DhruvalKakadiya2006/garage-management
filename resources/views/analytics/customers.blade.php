<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-user-group class="w-6 h-6 text-primary" />
                Customers Analytics
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
                :action="route('analytics.customers')" 
            />

            <div class="bg-white shadow-sm rounded-xl p-6">
                <h3 class="font-semibold text-gray-900 mb-1">Customers Ranked by Amount Paid</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Based on Job Cards and Counter Invoices created between 
                    <strong>{{ \Carbon\Carbon::parse($fromDate)->format('d/m/Y') }}</strong> and 
                    <strong>{{ \Carbon\Carbon::parse($toDate)->format('d/m/Y') }}</strong>.
                    Amount paid is the total of all payments received against those documents;
                    visit count is the number of documents raised for the customer in the range.
                </p>

                @if($customers->isEmpty())
                    <p class="text-sm text-gray-500">No customer activity found in this date range.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">#</th>
                                    <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Customer</th>
                                    <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Type</th>
                                    <th class="align-middle px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">Visits</th>
                                    <th class="align-middle px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">Amount Paid</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($customers as $index => $row)
                                <tr class="hover:bg-muted/50">
                                    <td class="align-middle px-6 py-2 whitespace-nowrap text-gray-500">{{ $index + 1 }}</td>
                                    <td class="align-middle px-6 py-2">
                                        <div class="font-medium text-gray-900">{{ $row['customer']->display_name }}</div>
                                        <div class="text-xs text-gray-500">{{ $row['customer']->mobile ?? '-' }}</div>
                                    </td>
                                    <td class="align-middle px-6 py-2 whitespace-nowrap text-gray-600 capitalize">
                                        {{ str_replace('-', ' ', $row['customer']->customer_type) }}
                                    </td>
                                    <td class="align-middle px-6 py-2 whitespace-nowrap text-right text-gray-900 font-medium">
                                        {{ $row['visits'] }}
                                    </td>
                                    <td class="align-middle px-6 py-2 whitespace-nowrap text-right text-gray-900 font-medium">
                                        {{ config('garage.currency_symbol') }}{{ number_format($row['total_paid'], 2) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <!-- Revenue by Customer Type Doughnut -->
            @if($revenueByCustomerType->count() > 0)
            <div class="bg-white shadow-sm rounded-xl p-6 mt-6">
                <h3 class="font-semibold text-gray-900 mb-3">Revenue by Customer Type</h3>
                <div class="overflow-x-auto">
                    <canvas id="customerTypeDoughnutChart" class="w-full" style="max-height: 400px;"></canvas>

                    <script>
                        // Map customer types to human-readable labels from config
                        const customerTypeLabels = @json(config('garage.option_lists.customer_type'));
                        // Also add walk_in label
                        const allTypeLabels = {
                            regular: 'Regular',
                            counter: 'Counter',
                            walk_in: 'Walk-in'
                        };
                        // Build data from the pre-passed arrays
                        const typeData = @json($revenueByCustomerTypeData);
                        const typeLabels = @json($revenueByCustomerTypeLabels);
                        const totalRevenue = {{ $revenueByCustomerType->sum('total') }};

                        document.addEventListener('DOMContentLoaded', () => {
                            if (typeof Chart === 'undefined') {
                                return;
                            }

                            const ctx = document.getElementById('customerTypeDoughnutChart').getContext('2d');
                            new Chart(ctx, {
                                type: 'doughnut',
                                data: {
                                    labels: typeLabels,
                                    datasets: [{
                                        data: typeData,
                                        backgroundColor: [
                                            'rgba(34, 197, 94, 0.7)',
                                            'rgba(59, 130, 246, 0.7)',
                                            'rgba(251, 191, 36, 0.7)'
                                        ]
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
                                                font: {
                                                    size: 11
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
                                                    let val = context.parsed;
                                                    label += '₹' + val.toLocaleString('en-IN', {
                                                        minimumFractionDigits: 2,
                                                        maximumFractionDigits: 2
                                                    });
                                                    label += ' (' + (totalRevenue > 0 ? (val / totalRevenue * 100).toFixed(1) : 0) + '%)';
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
            @endif

        </div>
    </div>
</x-app-layout>
