@php
    $profile = \App\Models\CompanyProfile::profile();
    // Use "Rs." for PDF compatibility (₹ symbol not supported in core PDF fonts)
    $currency = 'Rs. ';
    $money = fn ($value) => $currency . number_format((float) $value, 2);
    $trimGst = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');

    $subtotalBeforeDiscount = (float) $counterInvoice->items->sum(function($item) {
        return $item->quantity * $item->mrp;
    });
    
    $itemDiscounts = (float) $counterInvoice->items->sum(function($item) {
        $lineSubtotal = $item->quantity * $item->mrp;
        return ($lineSubtotal * $item->discount_percent) / 100;
    });
    
    $subtotal = $subtotalBeforeDiscount - $itemDiscounts;
    $discountPercent = (float) $counterInvoice->discount_percent;
    $discountAmount = (float) $counterInvoice->discount_amount;
    $cgst = (float) $counterInvoice->cgst_amount;
    $sgst = (float) $counterInvoice->sgst_amount;
    $roundOff = (float) $counterInvoice->round_off_amount;
    $grandTotal = (float) $counterInvoice->grand_total;

    $customer = $counterInvoice->customer;
    $isWalkin = !$customer;

    // Recompute the GST split exactly as GstCalculator does, grouped by
    // HSN + rate, so the summary rows reconcile with the document totals.
    $discountRatio = $subtotal > 0 ? $discountAmount / $subtotal : 0;
    $hsnRows = [];
    if ($counterInvoice->gst_applicable) {
        $collect = function ($lineTotal, $rate, $hsn, $description) use (&$hsnRows, $discountRatio) {
            $rate = (float) $rate;
            if ($rate <= 0) {
                return;
            }
            $key = ($hsn ?: '—') . '|' . $rate;
            $taxable = (float) $lineTotal * (1 - $discountRatio);
            $half = round($taxable * $rate / 200, 2);
            $hsnRows[$key]['hsn'] = $hsn ?: '—';
            $hsnRows[$key]['descriptions'][$description] = true;
            $hsnRows[$key]['rate'] = $rate;
            $hsnRows[$key]['taxable'] = ($hsnRows[$key]['taxable'] ?? 0) + $taxable;
            $hsnRows[$key]['cgst'] = ($hsnRows[$key]['cgst'] ?? 0) + $half;
            $hsnRows[$key]['sgst'] = ($hsnRows[$key]['sgst'] ?? 0) + $half;
        };

        foreach ($counterInvoice->items as $item) {
            $collect($item->line_total, $item->sparePart->gst_rate, $item->sparePart->hsn_code, $item->sparePart->part_name);
        }
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="html; charset=utf-8"/>
    <title>COUNTER INVOICE — {{ $counterInvoice->invoice_no }}</title>
    <style>
        @include('pdf.partials.styles')
    </style>
</head>
<body>

    @include('pdf.partials.company-header', [
        'documentTitle' => 'COUNTER INVOICE',
        'docMeta' => [
            'Invoice No' => $counterInvoice->invoice_no,
            'Date' => $counterInvoice->invoice_date->format('d/m/Y'),
        ],
    ])

    <div class="section">
        <table class="grid-2">
            <tr>
                <td style="width:50%;">
                    <div class="section-title">Bill To</div>
                    @if ($isWalkin)
                        <div class="value">{{ $counterInvoice->walkin_name ?: 'Walk-in Customer' }}</div>
                        @if ($counterInvoice->walkin_mobile)
                            <div class="company-line">Mobile: {{ $counterInvoice->walkin_mobile }}</div>
                        @endif
                    @else
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
                    @endif
                </td>
                <td style="width:50%;">
                    <div class="section-title">Payment Details</div>
                    <div><span class="label">Payment Status:</span> <span class="value">{{ ucwords(str_replace('_', ' ', $counterInvoice->payment_status_label)) }}</span></div>
                    @if ($counterInvoice->payment_mode)
                        <div><span class="label">Payment Mode:</span> <span class="value">{{ ucwords(str_replace('_', ' ', $counterInvoice->payment_mode)) }}</span></div>
                    @endif
                    @if ($counterInvoice->payments_sum_amount > 0)
                        <div><span class="label">Amount Paid:</span> <span class="value">{{ $money($counterInvoice->payments_sum_amount) }}</span></div>
                        @if ($counterInvoice->balance_due > 0)
                            <div><span class="label">Balance Due:</span> <span class="value">{{ $money($counterInvoice->balance_due) }}</span></div>
                        @endif
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Items</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:4%;">#</th>
                    <th style="width:32%;">Part</th>
                    <th style="width:12%;">HSN</th>
                    <th class="right" style="width:8%;">Qty</th>
                    <th style="width:8%;">Unit</th>
                    <th class="right" style="width:12%;">MRP</th>
                    <th class="right" style="width:10%;">Disc%</th>
                    <th class="right" style="width:8%;">GST%</th>
                    <th class="right" style="width:12%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($counterInvoice->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            {{ $item->sparePart->part_name }}
                            @if ($item->sparePart->part_code)
                                <div style="font-size:8pt; color:#333333;">{{ $item->sparePart->part_code }}</div>
                            @endif
                        </td>
                        <td>{{ $item->sparePart->hsn_code ?? '-' }}</td>
                        <td class="right">{{ $item->quantity }}</td>
                        <td>{{ $item->sparePart->unit ?? 'pcs' }}</td>
                        <td class="right">{{ $money($item->mrp) }}</td>
                        <td class="right">{{ $item->discount_percent > 0 ? $trimGst($item->discount_percent) : '—' }}</td>
                        <td class="right">{{ $counterInvoice->gst_applicable && $item->sparePart->gst_rate ? $trimGst($item->sparePart->gst_rate) : '—' }}</td>
                        <td class="right">{{ $money($item->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($counterInvoice->gst_applicable && count($hsnRows) > 0)
    <div class="section">
        <div class="section-title">GST Summary</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:12%;">HSN</th>
                    <th style="width:26%;">Description</th>
                    <th class="right" style="width:16%;">Taxable Value</th>
                    <th class="right" style="width:10%;">CGST %</th>
                    <th class="right" style="width:12%;">CGST Amt</th>
                    <th class="right" style="width:10%;">SGST %</th>
                    <th class="right" style="width:12%;">SGST Amt</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($hsnRows as $row)
                    <tr>
                        <td>{{ $row['hsn'] }}</td>
                        <td>{{ implode(', ', array_keys($row['descriptions'])) }}</td>
                        <td class="right">{{ $money($row['taxable']) }}</td>
                        <td class="right">{{ $trimGst($row['rate'] / 2) }}</td>
                        <td class="right">{{ $money($row['cgst']) }}</td>
                        <td class="right">{{ $trimGst($row['rate'] / 2) }}</td>
                        <td class="right">{{ $money($row['sgst']) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="2" style="font-weight:bold;">Total</td>
                    <td class="right" style="font-weight:bold;">{{ $money(array_sum(array_column($hsnRows, 'taxable'))) }}</td>
                    <td></td>
                    <td class="right" style="font-weight:bold;">{{ $money(array_sum(array_column($hsnRows, 'cgst'))) }}</td>
                    <td></td>
                    <td class="right" style="font-weight:bold;">{{ $money(array_sum(array_column($hsnRows, 'sgst'))) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <div class="section">
        <table class="totals">
            <tr>
                <td>Subtotal</td>
                <td class="right">{{ $money($subtotal) }}</td>
            </tr>
            @if ($discountAmount > 0)
            <tr>
                <td>Discount{{ $discountPercent > 0 ? ' (' . $trimGst($discountPercent) . '%)' : '' }}</td>
                <td class="right">-{{ $money($discountAmount) }}</td>
            </tr>
            @endif
            @if ($counterInvoice->gst_applicable)
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
                <td>Grand Total</td>
                <td class="right">{{ $money($grandTotal) }}</td>
            </tr>
        </table>
    </div>

    <div class="amount-in-words">
        Amount in Words: {{ \App\Support\IndianCurrency::inWords($grandTotal) }}
    </div>

    <div class="section terms">
        <div class="title">Payment Terms</div>
        <p>Payment is due on delivery. Please retain this invoice as proof of the transaction.</p>
        @if ($profile->terms_and_conditions)
            <p style="margin-top:4pt;">{{ $profile->terms_and_conditions }}</p>
        @endif
    </div>

    <div class="signatory">
        <div class="sign-line"></div>
        <div>For {{ $profile->company_name }}</div>
        <div>Authorised Signatory</div>
    </div>

    @include('pdf.partials.footer', [
        'documentLabel' => 'counter invoice',
        'companyName' => $profile->company_name,
    ])

</body>
</html>
