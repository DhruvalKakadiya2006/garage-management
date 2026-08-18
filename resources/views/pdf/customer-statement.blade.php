@php
    $company = config('garage.company');
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
        @font-face {
            font-family: 'Open Sans';
            font-style: normal;
            font-weight: 400;
            src: url('storage/fonts/OpenSans-Regular.ttf') format('truetype');
        }
        @font-face {
            font-family: 'Open Sans';
            font-style: normal;
            font-weight: 700;
            src: url('storage/fonts/OpenSans-Bold.ttf') format('truetype');
        }
        @font-face {
            font-family: 'Open Sans';
            font-style: italic;
            font-weight: 400;
            src: url('storage/fonts/OpenSans-Italic.ttf') format('truetype');
        }
        @font-face {
            font-family: 'Open Sans';
            font-style: italic;
            font-weight: 700;
            src: url('storage/fonts/OpenSans-BoldItalic.ttf') format('truetype');
        }
        @page {
            size: A4 portrait;
            margin: 14mm 14mm 18mm 14mm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Open Sans', sans-serif;
            font-size: 10pt;
            color: #1f2937;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        .doc-header {
            width: 100%;
            border-bottom: 2pt solid #145C9E;
            padding-bottom: 8pt;
            margin-bottom: 12pt;
            overflow: hidden;
        }
        .doc-title {
            font-size: 20pt;
            font-weight: bold;
            letter-spacing: 1pt;
            color: #145C9E;
        }
        .doc-meta { text-align: right; }
        .doc-meta .field { font-size: 9pt; color: #374151; margin-top: 2pt; }
        .company-name { font-size: 14pt; font-weight: bold; color: #111827; }
        .company-line { font-size: 8.5pt; color: #6b7280; }
        .doc-subtitle {
            font-size: 9pt;
            color: #6b7280;
            margin-bottom: 8pt;
        }
        .section {
            margin-bottom: 10pt;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 9.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
            color: #145C9E;
            border-bottom: 1pt solid #e5e7eb;
            padding-bottom: 2pt;
            margin-bottom: 5pt;
        }
        .grid-2 {
            width: 100%;
            border-collapse: collapse;
        }
        .grid-2 td {
            vertical-align: top;
            padding: 0 8pt 6pt 0;
        }
        .label { font-size: 8pt; text-transform: uppercase; color: #6b7280; }
        .value { font-size: 10pt; color: #111827; }
        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }
        table.data th {
            background: #f3f4f6;
            border: 1pt solid #e5e7eb;
            padding: 4pt 6pt;
            text-align: left;
            font-size: 8.5pt;
            text-transform: uppercase;
            color: #374151;
        }
        table.data td {
            border: 1pt solid #e5e7eb;
            padding: 4pt 6pt;
            vertical-align: top;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .totals {
            width: 55%;
            margin-left: auto;
            border-collapse: collapse;
            font-size: 9.5pt;
        }
        .totals td {
            padding: 3pt 6pt;
        }
        .totals .grand td {
            border-top: 2pt solid #145C9E;
            font-weight: bold;
            font-size: 11pt;
        }
        .note {
            margin-top: 10pt;
            font-size: 8.5pt;
            color: #6b7280;
        }
        .footer {
            position: fixed;
            bottom: -14mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #6b7280;
        }
    </style>
</head>
<body>

    <div class="doc-header">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="width:60%;">
                    <div class="company-name">{{ $company['name'] }}</div>
                    @if ($company['address_line1'])
                        <div class="company-line">{{ $company['address_line1'] }}</div>
                    @endif
                    @if ($company['address_line2'])
                        <div class="company-line">{{ $company['address_line2'] }}</div>
                    @endif
                    @if ($company['city'] || $company['state'] || $company['pin'])
                        <div class="company-line">{{ trim(implode(', ', array_filter([$company['city'], $company['state'], $company['pin']]))) }}</div>
                    @endif
                    @if ($company['gstin'])
                        <div class="company-line">GSTIN: {{ $company['gstin'] }}</div>
                    @endif
                    @if ($company['phone'])
                        <div class="company-line">Phone: {{ $company['phone'] }}</div>
                    @endif
                </td>
                <td class="doc-meta">
                    <div class="doc-title">CUSTOMER STATEMENT</div>
                    <div class="field"><strong>Customer:</strong> {{ $customer->display_name }}</div>
                    <div class="field"><strong>Period:</strong> {{ $rangeLabel }}</div>
                    <div class="field"><strong>Generated:</strong> {{ now()->format('d M Y H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

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
                                — <span style="color:#9ca3af;">(deleted record)</span>
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

    <div class="footer">
        This is a computer-generated customer statement. Generated on {{ now()->format('d M Y H:i') }}. — {{ $company['name'] }}
    </div>

</body>
</html>
