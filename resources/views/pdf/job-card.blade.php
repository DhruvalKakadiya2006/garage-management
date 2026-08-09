@php
    $isInvoice = $documentType === 'invoice';
    $company = config('garage.company');
    $currency = config('garage.currency_symbol');
    $money = fn ($value) => $currency . number_format((float) $value, 2);

    $partsTotal = (float) $jobCard->parts->sum('line_total');
    $laboursTotal = (float) $jobCard->labours->sum('line_total');
    $subtotal = $partsTotal + $laboursTotal;
    $discountPercent = (float) $jobCard->discount_percent;
    $discountAmount = (float) $jobCard->discount_amount;
    $cgst = (float) $jobCard->cgst_amount;
    $sgst = (float) $jobCard->sgst_amount;
    $roundOff = (float) $jobCard->round_off_amount;
    $grandTotal = (float) $jobCard->grand_total;

    $documentDate = $isInvoice
        ? ($jobCard->invoice_generated_at ?: now())
        : ($jobCard->estimate_generated_at ?: now());
    $validUntil = $documentDate->copy()->addDays((int) config('garage.estimate_validity_days'));

    $vehicle = $jobCard->vehicle;
    $customer = $jobCard->customer;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $isInvoice ? 'TAX INVOICE' : 'ESTIMATE' }} — {{ $jobCard->job_card_no }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm 14mm 18mm 14mm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
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
        .complaint { padding: 2pt 0; }
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
        .terms {
            margin-top: 14pt;
            font-size: 9pt;
            color: #374151;
        }
        .terms .title {
            font-weight: bold;
            color: #111827;
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
                    <div class="doc-title">{{ $isInvoice ? 'TAX INVOICE' : 'ESTIMATE' }}</div>
                    <div class="field"><strong>{{ $isInvoice ? 'Invoice' : 'Estimate' }} No:</strong> {{ $jobCard->job_card_no }}</div>
                    <div class="field"><strong>Date:</strong> {{ $documentDate->format('d M Y') }}</div>
                    <div class="field"><strong>Job Card:</strong> {{ $jobCard->job_card_no }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="doc-subtitle">
        {{ $isInvoice
            ? 'Tax invoice for services rendered under Job Card ' . $jobCard->job_card_no
            : 'Proposed quotation for the work described below. This is not a bill.' }}
    </div>

    <div class="section">
        <table class="grid-2">
            <tr>
                <td style="width:33%;">
                    <div class="section-title">Bill To</div>
                    <div class="value">{{ $customer->display_name }}</div>
                    @if ($customer->address)
                        <div class="company-line">{{ $customer->address }}</div>
                    @endif
                    @if ($customer->city)
                        <div class="company-line">{{ $customer->city }}</div>
                    @endif
                    @if ($customer->gstin)
                        <div class="company-line">GSTIN: {{ $customer->gstin }}</div>
                    @endif
                    @if ($customer->mobile)
                        <div class="company-line">Mobile: {{ $customer->mobile }}</div>
                    @endif
                </td>
                <td style="width:33%;">
                    <div class="section-title">Vehicle</div>
                    <div><span class="label">Registration:</span> <span class="value">{{ $vehicle->registration_no ?? 'NON-RTO' }}</span></div>
                    <div><span class="label">Make / Model:</span> <span class="value">{{ $vehicle->vehicleModel?->vehicleBrand?->name }} {{ $vehicle->vehicleModel?->name }}</span></div>
                    @if ($vehicle->color)
                        <div><span class="label">Color:</span> <span class="value">{{ $vehicle->color }}</span></div>
                    @endif
                    @if ($vehicle->chassis_no)
                        <div><span class="label">Chassis No:</span> <span class="value">{{ $vehicle->chassis_no }}</span></div>
                    @endif
                    @if ($jobCard->odometer_reading_km)
                        <div><span class="label">Odometer:</span> <span class="value">{{ number_format($jobCard->odometer_reading_km) }} km</span></div>
                    @endif
                </td>
                <td style="width:34%;">
                    <div class="section-title">Job Details</div>
                    <div><span class="label">Job Date:</span> <span class="value">{{ $jobCard->job_date->format('d M Y') }}</span></div>
                    <div><span class="label">Type:</span> <span class="value">{{ ucwords(str_replace('_', ' ', $jobCard->job_type)) }}</span></div>
                    <div><span class="label">Status:</span> <span class="value">{{ ucwords(str_replace('_', ' ', $jobCard->status)) }}</span></div>
                    @if ($jobCard->assignedTechnician)
                        <div><span class="label">Technician:</span> <span class="value">{{ $jobCard->assignedTechnician->name }}</span></div>
                    @endif
                    @if ($jobCard->supervisor)
                        <div><span class="label">Supervisor:</span> <span class="value">{{ $jobCard->supervisor->name }}</span></div>
                    @endif
                    @if ($jobCard->promised_delivery_date)
                        <div><span class="label">Promised Delivery:</span> <span class="value">{{ $jobCard->promised_delivery_date->format('d M Y') }}</span></div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    @if ($jobCard->complaints->count() > 0)
    <div class="section">
        <div class="section-title">Customer Complaints</div>
        @foreach ($jobCard->complaints as $complaint)
            <div class="complaint">• {{ $complaint->description }}</div>
        @endforeach
    </div>
    @endif

    @if ($jobCard->parts->count() > 0)
    <div class="section">
        <div class="section-title">Parts</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:4%;">#</th>
                    <th style="width:30%;">Part</th>
                    <th style="width:10%;">HSN</th>
                    <th class="right" style="width:8%;">Qty</th>
                    <th style="width:8%;">Unit</th>
                    <th class="right" style="width:10%;">MRP</th>
                    <th class="right" style="width:10%;">Labor</th>
                    <th class="right" style="width:8%;">GST%</th>
                    <th class="right" style="width:12%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($jobCard->parts as $part)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            {{ $part->sparePart->part_name }}
                            @if ($part->sparePart->part_code)
                                <div style="font-size:8pt; color:#6b7280;">{{ $part->sparePart->part_code }}</div>
                            @endif
                        </td>
                        <td>{{ $part->sparePart->hsn_code ?? '-' }}</td>
                        <td class="right">{{ $part->quantity }}</td>
                        <td>{{ $part->sparePart->unit ?? 'pcs' }}</td>
                        <td class="right">{{ $money($part->mrp) }}</td>
                        <td class="right">{{ $money($part->labor_charge) }}</td>
                        <td class="right">{{ $jobCard->gst_applicable && $part->sparePart->gst_rate ? rtrim(rtrim(number_format($part->sparePart->gst_rate, 2), '0'), '.') : '—' }}</td>
                        <td class="right">{{ $money($part->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if ($jobCard->labours->count() > 0)
    <div class="section">
        <div class="section-title">Labour</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:4%;">#</th>
                    <th style="width:34%;">Service</th>
                    <th style="width:22%;">Technician</th>
                    <th class="right" style="width:10%;">Qty</th>
                    <th class="right" style="width:12%;">Rate</th>
                    <th class="right" style="width:10%;">GST%</th>
                    <th class="right" style="width:12%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($jobCard->labours as $labour)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $labour->labourMaster->service_name }}</td>
                        <td>{{ $labour->technician->name ?? '—' }}</td>
                        <td class="right">{{ rtrim(rtrim(number_format($labour->qty, 2), '0'), '.') }}</td>
                        <td class="right">{{ $money($labour->rate) }}</td>
                        <td class="right">{{ $jobCard->gst_applicable && $labour->labourMaster->taxable && $labour->labourMaster->gst_rate ? rtrim(rtrim(number_format($labour->labourMaster->gst_rate, 2), '0'), '.') : '—' }}</td>
                        <td class="right">{{ $money($labour->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="section">
        <table class="totals">
            <tr>
                <td>Parts Total</td>
                <td class="right">{{ $money($partsTotal) }}</td>
            </tr>
            <tr>
                <td>Labour Total</td>
                <td class="right">{{ $money($laboursTotal) }}</td>
            </tr>
            <tr>
                <td>Subtotal</td>
                <td class="right">{{ $money($subtotal) }}</td>
            </tr>
            @if ($discountAmount > 0)
            <tr>
                <td>Discount{{ $discountPercent > 0 ? ' (' . rtrim(rtrim(number_format($discountPercent, 2), '0'), '.') . '%)' : '' }}</td>
                <td class="right">-{{ $money($discountAmount) }}</td>
            </tr>
            @endif
            @if ($jobCard->gst_applicable)
            <tr>
                <td>CGST</td>
                <td class="right">{{ $money($cgst) }}</td>
            </tr>
            <tr>
                <td>SGST</td>
                <td class="right">{{ $money($sgst) }}</td>
            </tr>
            @endif
            <tr>
                <td>Round Off</td>
                <td class="right">{{ $roundOff >= 0 ? '+' : '-' }}{{ $money(abs($roundOff)) }}</td>
            </tr>
            <tr class="grand">
                <td>{{ $isInvoice ? 'Grand Total' : 'Estimated Total' }}</td>
                <td class="right">{{ $money($grandTotal) }}</td>
            </tr>
        </table>
    </div>

    <div class="section terms">
        <div class="title">{{ $isInvoice ? 'Payment Terms' : 'Validity' }}</div>
        @if ($isInvoice)
            <p>Payment is due on delivery of the vehicle. Please retain this invoice as proof of the transaction.</p>
        @else
            <p>This estimate is valid for {{ config('garage.estimate_validity_days') }} days from the date of issue (until {{ $validUntil->format('d M Y') }}). Final invoice amount may change if additional work is required.</p>
        @endif
        @if ($jobCard->remarks)
            <p style="margin-top:4pt;"><strong>Remarks:</strong> {{ $jobCard->remarks }}</p>
        @endif
    </div>

    <div class="footer">
        This is a computer-generated {{ $isInvoice ? 'invoice' : 'estimate' }}. Generated on {{ now()->format('d M Y H:i') }}. — {{ $company['name'] }}
    </div>

</body>
</html>
