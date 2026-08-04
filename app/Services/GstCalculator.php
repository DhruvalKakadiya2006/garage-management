<?php

namespace App\Services;

class GstCalculator
{
    /**
     * Calculate document-level totals from line items.
     *
     * GST is always calculated on the post-discount taxable amount per line.
     * The discount (whether entered as a percent or a flat amount) is applied
     * proportionally to every line, then the document total is rounded to the
     * nearest whole rupee with the adjustment exposed as round_off_amount.
     *
     * @param array $lines Each line: ['line_total' => float, 'gst_rate' => ?float]
     * @param bool  $gstApplicable Whether GST applies at the document level
     * @param float $discountPercent Document discount percent (0 to disable)
     * @param float $discountAmount  Manual discount amount (used when percent is 0)
     *
     * @return array{subtotal: float, discount_percent: float, discount_amount: float,
     *               cgst_amount: float, sgst_amount: float, round_off_amount: float,
     *               grand_total: float}
     */
    public function calculate(array $lines, bool $gstApplicable, $discountPercent = 0, $discountAmount = 0): array
    {
        $subtotal = 0;
        foreach ($lines as $line) {
            $subtotal += (float) $line['line_total'];
        }

        $discountPercent = (float) ($discountPercent ?? 0);
        $discountAmount = (float) ($discountAmount ?? 0);
        if ($discountPercent > 0) {
            $discountAmount = ($subtotal * $discountPercent) / 100;
        }

        $discountRatio = $subtotal > 0 ? $discountAmount / $subtotal : 0;

        $totalCgst = 0;
        $totalSgst = 0;

        foreach ($lines as $line) {
            $lineTotal = (float) $line['line_total'];
            $taxableLineAmount = $lineTotal * (1 - $discountRatio);

            // No tax when GST is off at document level, or when the line has no rate
            if ($gstApplicable && !empty($line['gst_rate'])) {
                $tax = $taxableLineAmount * ((float) $line['gst_rate'] / 100);
                $half = round($tax / 2, 2);
                $totalCgst += $half;
                $totalSgst += $half;
            }
        }

        $afterDiscount = $subtotal - $discountAmount;
        $totalCgst = round($totalCgst, 2);
        $totalSgst = round($totalSgst, 2);

        $totalBeforeRounding = $afterDiscount + $totalCgst + $totalSgst;
        $roundedTotal = round($totalBeforeRounding);
        $roundOffAmount = round($roundedTotal - $totalBeforeRounding, 2);

        return [
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'cgst_amount' => $totalCgst,
            'sgst_amount' => $totalSgst,
            'round_off_amount' => $roundOffAmount,
            'grand_total' => $roundedTotal,
        ];
    }
}
