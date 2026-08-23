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

class AnalyticsController extends Controller
{
    /**
     * Show the Analytics landing page with a snapshot of key figures.
     */
    public function index()
    {
        $today = Carbon::now();

        // Outstanding = sum over all billed documents of (grand_total - amount paid).
        $jobCardOutstanding = JobCard::query()
            ->whereNotNull('grand_total')
            ->where('status', 'delivered')
            ->withSum('payments as amount_paid', 'amount')
            ->get(['id', 'grand_total'])
            ->sum(fn ($card) => max(0, (float) $card->grand_total - (float) $card->amount_paid));

        $counterOutstanding = CounterInvoice::query()
            ->withSum('payments as amount_paid', 'amount')
            ->get(['id', 'grand_total'])
            ->sum(fn ($invoice) => max(0, (float) $invoice->grand_total - (float) $invoice->amount_paid));

        $totalOutstanding = round($jobCardOutstanding + $counterOutstanding, 2);

        $openJobCardsCount = JobCard::whereNotIn('status', ['delivered', 'cancelled'])->count();

        $revenueThisMonth = (float) JobCard::where('status', 'delivered')
            ->whereDate('job_date', '>=', $today->startOfMonth()->toDateString())
            ->whereDate('job_date', '<=', $today->toDateString())
            ->sum('grand_total')
            + (float) CounterInvoice::whereDate('invoice_date', '>=', $today->startOfMonth()->toDateString())
                ->whereDate('invoice_date', '<=', $today->toDateString())
                ->sum('grand_total');

        return view('analytics.index', compact('totalOutstanding', 'openJobCardsCount', 'revenueThisMonth'));
    }

    /**
     * Revenue Analytics - revenue breakdown by source, daily trend and a
     * rolling 6-month trend for trajectory.
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

        // Rolling 6-month revenue trend (trailing 5 full months + current month),
        // independent of the applied date filter, for a sense of trajectory.
        $trendMonths = collect(range(5, 0))
            ->map(fn (int $i) => Carbon::now()->startOfMonth()->subMonths($i));

        $trendStart = $trendMonths->first()->startOfMonth()->toDateString();

        $jobCardsMonthly = JobCard::where('status', 'delivered')
            ->whereDate('job_date', '>=', $trendStart)
            ->selectRaw("strftime('%Y-%m', job_date) as month, SUM(grand_total) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $counterInvoicesMonthly = CounterInvoice::whereDate('invoice_date', '>=', $trendStart)
            ->selectRaw("strftime('%Y-%m', invoice_date) as month, SUM(grand_total) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $revenueTrend = $trendMonths->map(function (Carbon $month) use ($jobCardsMonthly, $counterInvoicesMonthly) {
            $key = $month->format('Y-m');

            return [
                'label' => $month->format('M y'),
                'total' => (float) ($jobCardsMonthly[$key] ?? 0) + (float) ($counterInvoicesMonthly[$key] ?? 0),
            ];
        });

        return view('analytics.revenue', compact(
            'fromDate',
            'toDate',
            'totalRevenue',
            'jobCardsRevenue',
            'counterInvoicesRevenue',
            'revenueByJobType',
            'revenueByDay',
            'revenueTrend'
        ));
    }

    /**
     * Job Cards Analytics - job cards counted by status and job type, plus
     * average job value of delivered cards.
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

        $deliveredCards = JobCard::whereDate('job_date', '>=', $fromDate)
            ->whereDate('job_date', '<=', $toDate)
            ->where('status', 'delivered');

        $deliveredCount = (clone $deliveredCards)->count();
        $deliveredRevenue = (float) (clone $deliveredCards)->sum('grand_total');
        $averageJobValue = $deliveredCount > 0 ? round($deliveredRevenue / $deliveredCount, 2) : 0.0;

        return view('analytics.job-cards', compact(
            'fromDate',
            'toDate',
            'statusCounts',
            'jobTypeCounts',
            'totalJobCards',
            'deliveredCount',
            'deliveredRevenue',
            'averageJobValue'
        ));
    }

    /**
     * Inventory Analytics - stock levels, low stock flags, and parts usage.
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

        return view('analytics.inventory', compact('fromDate', 'toDate', 'inventory', 'lowStockCount', 'totalStockValue', 'totalUnits'));
    }

    /**
     * Payments Analytics - payments collected grouped by mode, plus each payment.
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

        return view('analytics.payments', compact('fromDate', 'toDate', 'payments', 'totalPayments', 'paymentsByMode'));
    }

    /**
     * Customers Analytics - customers ranked by amount paid, plus revenue
     * split by customer type (regular / counter / walk-in).
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

        // Revenue split by customer type (delivered job cards + all counter
        // invoices, matching the revenue report's definition of revenue).
        $jobCardsByType = JobCard::whereDate('job_date', '>=', $fromDate)
            ->whereDate('job_date', '<=', $toDate)
            ->where('status', 'delivered')
            ->join('customers', 'customers.id', '=', 'job_cards.customer_id')
            ->selectRaw('customers.customer_type as customer_type, SUM(job_cards.grand_total) as total')
            ->groupBy('customers.customer_type')
            ->pluck('total', 'customer_type');

        $counterByType = CounterInvoice::whereDate('invoice_date', '>=', $fromDate)
            ->whereDate('invoice_date', '<=', $toDate)
            ->leftJoin('customers', 'customers.id', '=', 'counter_invoices.customer_id')
            ->selectRaw("COALESCE(customers.customer_type, 'walk_in') as customer_type, SUM(counter_invoices.grand_total) as total")
            ->groupBy(DB::raw("COALESCE(customers.customer_type, 'walk_in')"))
            ->pluck('total', 'customer_type');

        $revenueByCustomerType = collect([
            ['key' => 'regular', 'label' => 'Regular'],
            ['key' => 'counter', 'label' => 'Counter'],
            ['key' => 'walk_in', 'label' => 'Walk-in'],
        ])->map(function (array $type) use ($jobCardsByType, $counterByType) {
            $type['total'] = round(
                (float) ($jobCardsByType[$type['key']] ?? 0) + (float) ($counterByType[$type['key']] ?? 0),
                2
            );

            return $type;
        })->reject(fn (array $type) => $type['total'] <= 0)->values();

        // Pre-build data arrays for the chart (avoid @foreach inside <script>)
        $revenueByCustomerTypeData = $revenueByCustomerType->pluck('total')->toArray();
        $revenueByCustomerTypeLabels = $revenueByCustomerType->pluck('label')->toArray();

        return view('analytics.customers', compact('fromDate', 'toDate', 'customers', 'revenueByCustomerType',
            'revenueByCustomerTypeData', 'revenueByCustomerTypeLabels'));
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
        if (! $customerId) {
            return;
        }

        if (! isset($totals[$customerId])) {
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
