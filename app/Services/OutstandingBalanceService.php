<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OutstandingBalanceService
{
    /**
     * Total outstanding dues across every billed document (job cards and
     * counter invoices).
     *
     * The balance is clamped per document at zero, so one customer's
     * overpayment can never offset another customer's unpaid balance — this
     * is the single canonical definition shared by the dashboard and the
     * analytics page.
     *
     * withSum must stay UNALIASED so it produces the payments_sum_amount
     * attribute that the models' amount_paid accessors use as their
     * eager-loaded fast path; an aliased form silently falls back to a live
     * SUM query per row.
     */
    public function total(): float
    {
        $jobCardOutstanding = (float) DB::table('job_cards')
            ->whereNotNull('grand_total')
            ->whereNull('deleted_at')
            ->leftJoin(
                DB::raw('(SELECT payable_id, SUM(amount) as paid FROM payments WHERE payable_type = \'App\Models\JobCard\' AND deleted_at IS NULL GROUP BY payable_id) as jc_paid'),
                'jc_paid.payable_id',
                '=',
                'job_cards.id'
            )
            ->selectRaw('COALESCE(SUM(CASE WHEN job_cards.grand_total - COALESCE(jc_paid.paid, 0) > 0 THEN job_cards.grand_total - COALESCE(jc_paid.paid, 0) ELSE 0 END), 0) as total')
            ->value('total');

        $counterOutstanding = (float) DB::table('counter_invoices')
            ->whereNotNull('grand_total')
            ->leftJoin(
                DB::raw('(SELECT payable_id, SUM(amount) as paid FROM payments WHERE payable_type = \'App\Models\CounterInvoice\' AND deleted_at IS NULL GROUP BY payable_id) as ci_paid'),
                'ci_paid.payable_id',
                '=',
                'counter_invoices.id'
            )
            ->selectRaw('COALESCE(SUM(CASE WHEN counter_invoices.grand_total - COALESCE(ci_paid.paid, 0) > 0 THEN counter_invoices.grand_total - COALESCE(ci_paid.paid, 0) ELSE 0 END), 0) as total')
            ->value('total');

        return round($jobCardOutstanding + $counterOutstanding, 2);
    }
}
