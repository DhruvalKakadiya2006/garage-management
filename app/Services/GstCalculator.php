<?php

namespace App\Services;

/**
 * GST Calculator Service
 *
 * Calculates document-level totals from line items following Indian GST rules.
 *
 * Calculation Order (per AI_AGENT_CONTEXT.md §6):
 * 1. Calculate subtotal from all line items
 * 2. Apply discount (proportionally to each line's taxable amount)
 * 3. Calculate GST on the post-discount amount per line (using line's gst_rate)
 * 4. Split GST equally into CGST and SGST
 * 5. Round final total to nearest whole rupee
 * 6. Record round-off adjustment as separate line item
 *
 * Example:
 *   Subtotal: ₹1,000
 *   Discount: 10% = ₹100
 *   After Discount: ₹900
 *   GST @ 18%: ₹162 (CGST ₹81 + SGST ₹81)
 *   Before Rounding: ₹1,062.00
 *   Grand Total: ₹1,062 (round-off: ₹0.00)
 *
 * Security: Discount amount can never exceed subtotal (validated upstream).
 */
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
     * @param  array  $lines  Each line: ['line_total' => float, 'gst_rate' => ?float]
     * @param  bool  $gstApplicable  Whether GST applies at the document level
     * @param  float  $discountPercent  Document discount percent (0 to disable)
     * @param  float  $discountAmount  Manual discount amount (used when percent is 0)
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
            if ($gstApplicable && ! empty($line['gst_rate'])) {
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
