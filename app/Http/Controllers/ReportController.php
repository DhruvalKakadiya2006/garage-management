<?php

namespace App\Http\Controllers;

use App\Models\CounterInvoice;
use App\Models\JobCard;
use App\Models\Payment;
use App\Models\SparePart;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    /**
     * Show reports landing page with links to all reports.
     */
    public function index()
    {
        return view('reports.index');
    }

    /**
     * Revenue Report - shows revenue breakdown by source and daily trend.
     */
    public function revenue(Request $request)
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);

        // Get Job Cards revenue (only delivered status counts as revenue)
        $jobCardsRevenue = JobCard::whereDate('job_date', '>=', $fromDate)
            ->whereDate('job_date', '<=', $toDate)
            ->where('status', 'delivered')
            ->sum('grand_total');

        // Get Counter Invoices revenue
        $counterInvoicesRevenue = CounterInvoice::whereDate('invoice_date', '>=', $fromDate)
            ->whereDate('invoice_date', '<=', $toDate)
            ->sum('grand_total');

        // Total revenue
        $totalRevenue = $jobCardsRevenue + $counterInvoicesRevenue;

        // Revenue by job type (from Job Cards only)
        $revenueByJobType = JobCard::whereDate('job_date', '>=', $fromDate)
            ->whereDate('job_date', '<=', $toDate)
            ->where('status', 'delivered')
            ->selectRaw('job_type, SUM(grand_total) as total')
            ->groupBy('job_type')
            ->orderByDesc('total')
            ->get();

        // One grouped query per source (no per-day N+1 loop), merged by date in PHP
        $jobCardsDaily = JobCard::selectRaw('DATE(job_date) as day, SUM(grand_total) as total')
            ->whereDate('job_date', '>=', $fromDate)
            ->whereDate('job_date', '<=', $toDate)
            ->where('status', 'delivered')
            ->groupBy(DB::raw('DATE(job_date)'))
            ->pluck('total', 'day');

        $counterInvoicesDaily = CounterInvoice::selectRaw('DATE(invoice_date) as day, SUM(grand_total) as total')
            ->whereDate('invoice_date', '>=', $fromDate)
            ->whereDate('invoice_date', '<=', $toDate)
            ->groupBy(DB::raw('DATE(invoice_date)'))
            ->pluck('total', 'day');

        // Build a continuous day-by-day series within the range
        $revenueByDay = collect();
        $currentDate = Carbon::parse($fromDate);
        $endDate = Carbon::parse($toDate);

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');

            $revenueByDay->push([
                'date' => $currentDate->format('M d'),
                'date_full' => $dateStr,
                'total' => (float) ($jobCardsDaily[$dateStr] ?? 0) + (float) ($counterInvoicesDaily[$dateStr] ?? 0),
            ]);

            $currentDate->addDay();
        }

        return view('reports.revenue', compact(
            'fromDate',
            'toDate',
            'totalRevenue',
            'jobCardsRevenue',
            'counterInvoicesRevenue',
            'revenueByJobType',
            'revenueByDay'
        ));
    }

    /**
     * Job Cards Report - job cards counted by status and by job type.
     */
    public function jobCards(Request $request)
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);

        $statusCounts = JobCard::whereDate('job_date', '>=', $fromDate)
            ->whereDate('job_date', '<=', $toDate)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();

        $jobTypeCounts = JobCard::whereDate('job_date', '>=', $fromDate)
            ->whereDate('job_date', '<=', $toDate)
            ->selectRaw('job_type, COUNT(*) as count')
            ->groupBy('job_type')
            ->orderByDesc('count')
            ->get();

        $totalJobCards = $statusCounts->sum('count');

        return view('reports.job-cards', compact('fromDate', 'toDate', 'statusCounts', 'jobTypeCounts', 'totalJobCards'));
    }

    /**
     * Inventory Report - stock levels, low stock flags, and parts usage.
     */
    public function inventory(Request $request)
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);

        // Units of each part used from Job Cards in the date range
        $jobCardUsage = DB::table('job_card_parts')
            ->join('job_cards', 'job_cards.id', '=', 'job_card_parts.job_card_id')
            ->whereDate('job_cards.job_date', '>=', $fromDate)
            ->whereDate('job_cards.job_date', '<=', $toDate)
            ->select('job_card_parts.spare_part_id', DB::raw('SUM(job_card_parts.quantity) as times_used'))
            ->groupBy('job_card_parts.spare_part_id')
            ->pluck('times_used', 'spare_part_id');

        // Units of each part used from Counter Invoices in the date range
        $counterUsage = DB::table('counter_invoice_items')
            ->join('counter_invoices', 'counter_invoices.id', '=', 'counter_invoice_items.counter_invoice_id')
            ->whereDate('counter_invoices.invoice_date', '>=', $fromDate)
            ->whereDate('counter_invoices.invoice_date', '<=', $toDate)
            ->select('counter_invoice_items.spare_part_id', DB::raw('SUM(counter_invoice_items.quantity) as times_used'))
            ->groupBy('counter_invoice_items.spare_part_id')
            ->pluck('times_used', 'spare_part_id');

        $inventory = SparePart::orderBy('part_name')->get()
            ->map(fn (SparePart $part) => [
                'id' => $part->id,
                'part_name' => $part->part_name,
                'part_code' => $part->part_code,
                'category' => $part->category,
                'unit' => $part->unit,
                'purchase_price' => (float) $part->purchase_price,
                'mrp' => (float) $part->mrp,
                'labor_charge' => (float) $part->labor_charge,
                'current_stock_qty' => (int) $part->current_stock_qty,
                'reorder_level' => (int) $part->reorder_level,
                'times_used' => (int) ($jobCardUsage[$part->id] ?? 0) + (int) ($counterUsage[$part->id] ?? 0),
            ])
            ->map(fn (array $item) => $item + [
                'low_stock' => $item['current_stock_qty'] <= $item['reorder_level'],
                'stock_value' => round($item['current_stock_qty'] * $item['purchase_price'], 2),
            ])
            ->sortByDesc('times_used')
            ->values();

        $lowStockCount = $inventory->where('low_stock', true)->count();
        $totalStockValue = $inventory->sum('stock_value');
        $totalUnits = $inventory->sum('current_stock_qty');

        return view('reports.inventory', compact('fromDate', 'toDate', 'inventory', 'lowStockCount', 'totalStockValue', 'totalUnits'));
    }

    /**
     * Payments Report - payments collected grouped by mode, plus each payment.
     */
    public function payments(Request $request)
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);

        $payments = Payment::with('payable.customer')
            ->whereDate('payment_date', '>=', $fromDate)
            ->whereDate('payment_date', '<=', $toDate)
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalPayments = round($payments->sum('amount'), 2);

        $modeOrder = array_keys(config('garage.option_lists.payment_mode'));
        $paymentsByMode = collect($modeOrder)->mapWithKeys(function (string $mode) use ($payments) {
            $modePayments = $payments->where('payment_mode', $mode);

            return [$mode => [
                'count' => $modePayments->count(),
                'total' => round($modePayments->sum('amount'), 2),
            ]];
        });

        return view('reports.payments', compact('fromDate', 'toDate', 'payments', 'totalPayments', 'paymentsByMode'));
    }

    /**
     * Customers Report - customers ranked by amount paid, with visit count.
     */
    public function customers(Request $request)
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);

        // Documents created within the date range
        $jobCards = JobCard::with('customer')
            ->whereDate('job_date', '>=', $fromDate)
            ->whereDate('job_date', '<=', $toDate)
            ->get(['id', 'customer_id']);

        $counterInvoices = CounterInvoice::with('customer')
            ->whereDate('invoice_date', '>=', $fromDate)
            ->whereDate('invoice_date', '<=', $toDate)
            ->get(['id', 'customer_id']);

        // Payments attached to those documents, summed per document (no N+1)
        $jobCardPayments = $this->paymentTotalsFor(JobCard::class, $jobCards->pluck('id'));
        $counterInvoicePayments = $this->paymentTotalsFor(CounterInvoice::class, $counterInvoices->pluck('id'));

        $customerTotals = [];
        foreach ($jobCards as $jobCard) {
            $this->accumulateCustomer($customerTotals, $jobCard->customer_id, $jobCard->customer, (float) ($jobCardPayments[$jobCard->id] ?? 0));
        }
        foreach ($counterInvoices as $invoice) {
            $this->accumulateCustomer($customerTotals, $invoice->customer_id, $invoice->customer, (float) ($counterInvoicePayments[$invoice->id] ?? 0));
        }

        $customers = collect($customerTotals)
            ->map(fn (array $row) => [
                'customer' => $row['customer'],
                'total_paid' => round($row['total_paid'], 2),
                'visits' => $row['visits'],
            ])
            ->sortByDesc('total_paid')
            ->values();

        return view('reports.customers', compact('fromDate', 'toDate', 'customers'));
    }

    /**
     * Validate the from/to date filter and apply consistent defaults
     * (first day of the current month through today) when not supplied.
     *
     * @return array{0: string, 1: string} [$fromDate, $toDate] as Y-m-d
     */
    private function resolveDateRange(Request $request): array
    {
        $validated = $request->validate([
            'from_date' => 'nullable|date|required_with:to_date',
            'to_date' => 'nullable|date|required_with:from_date',
        ], [], ['from_date' => 'From Date', 'to_date' => 'To Date']);

        $fromDate = $validated['from_date'] ?? Carbon::now()->startOfMonth()->toDateString();
        $toDate = $validated['to_date'] ?? Carbon::now()->toDateString();

        if (Carbon::parse($toDate)->lt(Carbon::parse($fromDate))) {
            throw ValidationException::withMessages([
                'to_date' => 'The To Date must be on or after the From Date.',
            ]);
        }

        return [$fromDate, $toDate];
    }

    /**
     * Sum payments grouped by payable_id for one payable type.
     */
    private function paymentTotalsFor(string $payableType, $payableIds): array
    {
        if ($payableIds->isEmpty()) {
            return [];
        }

        return Payment::where('payable_type', $payableType)
            ->whereIn('payable_id', $payableIds)
            ->selectRaw('payable_id, SUM(amount) as total')
            ->groupBy('payable_id')
            ->pluck('total', 'payable_id')
            ->all();
    }

    /**
     * Add one document's payment to the running customer totals map.
     */
    private function accumulateCustomer(array &$totals, ?int $customerId, $customer, float $paid): void
    {
        if (!$customerId) {
            return;
        }

        if (!isset($totals[$customerId])) {
            $totals[$customerId] = [
                'customer' => $customer,
                'total_paid' => 0.0,
                'visits' => 0,
            ];
        }

        $totals[$customerId]['total_paid'] += $paid;
        $totals[$customerId]['visits']++;
    }
}
