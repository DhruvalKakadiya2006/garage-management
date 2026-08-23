@php
    $profile = \App\Models\CompanyProfile::profile();
    $currency = config('garage.currency_symbol');
    $money = fn ($value) => $currency . number_format((float) $value, 2);
    $outstandingTo = fn ($doc) => max(0, (float) $doc->grand_total - (float) ($doc->paid_to_sum_amount ?? 0));
    $rangeLabel = $from->format('d M Y') . ' to ' . $to->format('d M Y');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Customer Statement — {{ $customer->display_name }}</title>
    <style>
        @include('pdf.partials.styles')
    </style>
</head>
<body>

    @include('pdf.partials.company-header', [
        'documentTitle' => 'CUSTOMER STATEMENT',
        'docMeta' => [
            'Customer' => $customer->display_name,
            'Period' => $rangeLabel,
            'Generated' => now()->format('d M Y H:i'),
        ],
    ])

    <div class="doc-subtitle">
        Statement of account for {{ $customer->display_name }} for the period {{ $rangeLabel }}.
        Outstanding shown is the balance due as on {{ $to->format('d M Y') }}.
    </div>

    <div class="section">
        <div class="section-title">Customer Details</div>
        <table class="grid-2">
            <tr>
                <td style="width:33%;">
                    <div class="label">Name</div>
                    <div class="value">{{ $customer->display_name }}</div>
                </td>
                <td style="width:33%;">
                    <div class="label">Mobile</div>
                    <div class="value">{{ $customer->mobile ?? '—' }} @if ($customer->alt_mobile)<span class="company-line"> / {{ $customer->alt_mobile }}</span>@endif</div>
                </td>
                <td style="width:34%;">
                    <div class="label">Type</div>
                    <div class="value">{{ ucwords(str_replace('_', ' ', $customer->customer_type)) }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Email</div>
                    <div class="value">{{ $customer->email ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">Address</div>
                    <div class="value">{{ trim(implode(', ', array_filter([$customer->address, $customer->city]))) ?: '—' }}</div>
                </td>
                <td>
                    <div class="label">GSTIN</div>
                    <div class="value">{{ $customer->gstin ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Vehicles</div>
        @if ($customer->vehicles->isEmpty())
            <p class="value">No vehicles registered.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th style="width:26%;">Registration No.</th>
                        <th style="width:40%;">Make / Model</th>
                        <th style="width:18%;">Battery</th>
                        <th style="width:16%;">Color</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customer->vehicles as $vehicle)
                        <tr>
                            <td>{{ $vehicle->registration_no ?? 'NON-RTO' }}</td>
                            <td>{{ $vehicle->vehicleModel?->vehicleBrand?->name }} {{ $vehicle->vehicleModel?->name }}</td>
                            <td>{{ ucwords(str_replace('-', ' ', (string) ($vehicle->vehicleModel?->battery_type ?? '—'))) }}</td>
                            <td>{{ $vehicle->color ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if ($jobCards->count() > 0)
    <div class="section">
        <div class="section-title">Job Cards</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:14%;">Job Card</th>
                    <th style="width:14%;">Date</th>
                    <th style="width:20%;">Vehicle</th>
                    <th style="width:16%;">Job Type</th>
                    <th class="right" style="width:12%;">Billed</th>
                    <th class="right" style="width:12%;">Collected</th>
                    <th class="right" style="width:12%;">Outstanding</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($jobCards as $jobCard)
                    <tr>
                        <td>{{ $jobCard->job_card_no }}</td>
                        <td>{{ $jobCard->job_date->format('d M Y') }}</td>
                        <td>{{ $jobCard->vehicle?->registration_no ?? 'NON-RTO' }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $jobCard->job_type)) }}</td>
                        <td class="right">{{ $money($jobCard->grand_total) }}</td>
                        <td class="right">{{ $money($jobCard->collected_in_range_sum_amount ?? 0) }}</td>
                        <td class="right">{{ $money($outstandingTo($jobCard)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if ($counterInvoices->count() > 0)
    <div class="section">
        <div class="section-title">Counter Invoices</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:16%;">Invoice No.</th>
                    <th style="width:18%;">Date</th>
                    <th style="width:30%;">Customer</th>
                    <th class="right" style="width:12%;">Billed</th>
                    <th class="right" style="width:12%;">Collected</th>
                    <th class="right" style="width:12%;">Outstanding</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($counterInvoices as $invoice)
                    <tr>
                        <td>{{ $invoice->invoice_no }}</td>
                        <td>{{ $invoice->invoice_date->format('d M Y') }}</td>
                        <td>{{ $invoice->customer_id ? $customer->display_name : ($invoice->walkin_name ?? 'Walk-in') }}</td>
                        <td class="right">{{ $money($invoice->grand_total) }}</td>
                        <td class="right">{{ $money($invoice->collected_in_range_sum_amount ?? 0) }}</td>
                        <td class="right">{{ $money($outstandingTo($invoice)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if ($payments->count() > 0)
    <div class="section">
        <div class="section-title">Payments</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:14%;">Date</th>
                    <th style="width:16%;">Document</th>
                    <th style="width:16%;">Mode</th>
                    <th style="width:30%;">Reference</th>
                    <th class="right" style="width:24%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td>{{ $payment->payment_date->format('d M Y') }}</td>
                        <td>
                            @if ($payment->payable instanceof \App\Models\JobCard)
                                {{ $payment->payable->job_card_no }}
                            @elseif ($payment->payable)
                                {{ $payment->payable->invoice_no }}
                            @else
                                — <span style="color:#666666;">(deleted record)</span>
                            @endif
                        </td>
                        <td>{{ ucwords(str_replace('_', ' ', $payment->payment_mode)) }}</td>
                        <td>{{ $payment->reference_no ?? '—' }}</td>
                        <td class="right">{{ $money($payment->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="section">
        <table class="totals">
            <tr>
                <td>Total Billed ({{ $rangeLabel }})</td>
                <td class="right">{{ $money($billedInRange) }}</td>
            </tr>
            <tr>
                <td>Total Collected ({{ $rangeLabel }})</td>
                <td class="right">{{ $money($collectedInRange) }}</td>
            </tr>
            <tr class="grand">
                <td>Outstanding Balance (as on {{ $to->format('d M Y') }})</td>
                <td class="right">{{ $money($outstandingAsOf) }}</td>
            </tr>
        </table>
    </div>

    <div class="note">
        Outstanding balance is computed from all documents dated on or before {{ $to->format('d M Y') }}
        minus all payments received on or before that date. This statement is a summary of existing
        Job Cards, Counter Invoices and their payments; it is not an independent ledger.
    </div>

    @include('pdf.partials.footer', [
        'documentLabel' => 'customer statement',
        'companyName' => $profile->company_name,
    ])

</body>
</html>