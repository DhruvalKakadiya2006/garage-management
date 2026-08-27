<?php

namespace App\Http\Controllers;

use App\Models\CounterInvoice;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\JobCard;
use App\Models\Payment;
use App\Models\SparePart;
use App\Services\OutstandingBalanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnalyticsController extends Controller
{
    /**
     * Analytics hub — every graph/data directly on this tab (shadcn style).
     * Revenue & Sales · Operations · Money Owed · Customers · Technicians · Inventory
     * No navigation cards — only real charts and tables.
     */
    public function index()
    {
        $today = Carbon::now();
        $startOfMonth = $today->copy()->startOfMonth()->toDateString();
        $endOfMonth = $today->toDateString();

        // ── Snapshot KPIs (shared) ──
        $totalOutstanding = app(OutstandingBalanceService::class)->total();
        $openJobCardsCount = JobCard::whereNotIn('status', ['delivered', 'cancelled'])->count();
        $revenueThisMonth = (float) JobCard::where('status', 'delivered')->whereDate('job_date', '>=', $startOfMonth)->whereDate('job_date', '<=', $endOfMonth)->sum('grand_total')
            + (float) CounterInvoice::whereDate('invoice_date', '>=', $startOfMonth)->whereDate('invoice_date', '<=', $endOfMonth)->sum('grand_total');

        // ── Revenue & Sales ──
        // FIX: Revenue trend was showing a cumulative line because the JS was
        // re-using a single dataset without clearing, and the monthly query
        // used a stale Carbon range. Now we build three isolated per-period
        // series (daily / weekly / monthly) with fresh Carbon copies and no
        // running total.
        $dailyFrom = $today->copy()->subDays(29)->toDateString();
        $jobDaily = JobCard::selectRaw('DATE(job_date) as day, SUM(grand_total) as total')->whereDate('job_date', '>=', $dailyFrom)->whereDate('job_date', '<=', $endOfMonth)->where('status', 'delivered')->groupBy(DB::raw('DATE(job_date)'))->pluck('total', 'day');
        $invDaily = CounterInvoice::selectRaw('DATE(invoice_date) as day, SUM(grand_total) as total')->whereDate('invoice_date', '>=', $dailyFrom)->whereDate('invoice_date', '<=', $endOfMonth)->groupBy(DB::raw('DATE(invoice_date)'))->pluck('total', 'day');
        $revenueDailyTrend = collect();
        $cur = Carbon::parse($dailyFrom);
        $end = Carbon::parse($endOfMonth);
        while ($cur->lte($end)) {
            $d = $cur->toDateString();
            $revenueDailyTrend->push(['date' => $cur->format('M d'), 'total' => (float) ($jobDaily[$d] ?? 0) + (float) ($invDaily[$d] ?? 0)]);
            $cur = $cur->copy()->addDay();
        }
        // Weekly: last 12 weeks Monday→Sunday, per-week total (not cumulative)
        $weeklyFrom = $today->copy()->subWeeks(11)->startOfWeek()->toDateString();
        $jobWeekly = JobCard::where('status', 'delivered')->whereDate('job_date', '>=', $weeklyFrom)->whereDate('job_date', '<=', $endOfMonth)->selectRaw("strftime('%Y-%W', job_date) as wk, SUM(grand_total) as total")->groupBy('wk')->pluck('total', 'wk');
        $invWeekly = CounterInvoice::whereDate('invoice_date', '>=', $weeklyFrom)->whereDate('invoice_date', '<=', $endOfMonth)->selectRaw("strftime('%Y-%W', invoice_date) as wk, SUM(grand_total) as total")->groupBy('wk')->pluck('total', 'wk');
        $revenueWeeklyTrend = collect();
        $wcur = Carbon::parse($weeklyFrom)->startOfWeek();
        for ($i = 0; $i < 12; $i++) {
            $wkKey = $wcur->format('Y-W'); // matches SQLite strftime('%Y-%W', date)
            $label = $wcur->format('M d');
            $revenueWeeklyTrend->push(['date' => $label, 'total' => (float) ($jobWeekly[$wkKey] ?? 0) + (float) ($invWeekly[$wkKey] ?? 0)]);
            $wcur = $wcur->copy()->addWeek();
        }
        // 6-month monthly trend for Revenue & avg job value — use fresh Carbon copies to avoid mutation
        $trendMonths = collect(range(5, 0))->map(fn (int $i) => Carbon::now()->copy()->startOfMonth()->subMonths($i));
        $trendStart = $trendMonths->first()->copy()->toDateString();
        $jobMonthly = JobCard::where('status', 'delivered')->whereDate('job_date', '>=', $trendStart)->selectRaw("strftime('%Y-%m', job_date) as month, SUM(grand_total) as total")->groupBy('month')->pluck('total', 'month');
        $jobMonthlyCount = JobCard::where('status', 'delivered')->whereDate('job_date', '>=', $trendStart)->selectRaw("strftime('%Y-%m', job_date) as month, COUNT(*) as cnt")->groupBy('month')->pluck('cnt', 'month');
        $invMonthly = CounterInvoice::whereDate('invoice_date', '>=', $trendStart)->selectRaw("strftime('%Y-%m', invoice_date) as month, SUM(grand_total) as total")->groupBy('month')->pluck('total', 'month');
        $revenueMonthlyTrend = $trendMonths->map(fn (Carbon $m) => ['label' => $m->format('M y'), 'total' => (float) ($jobMonthly[$m->format('Y-m')] ?? 0) + (float) ($invMonthly[$m->format('Y-m')] ?? 0)]);
        $avgJobValueTrend = $trendMonths->map(fn (Carbon $m) => ['label' => $m->format('M y'), 'avg' => (($c = (int) ($jobMonthlyCount[$m->format('Y-m')] ?? 0)) > 0 ? round((float) ($jobMonthly[$m->format('Y-m')] ?? 0) / $c, 2) : 0)]);
        // Split by source (this month)
        $jobCardsRevenueMonth = (float) JobCard::where('status', 'delivered')->whereDate('job_date', '>=', $startOfMonth)->whereDate('job_date', '<=', $endOfMonth)->sum('grand_total');
        $counterRevenueMonth = (float) CounterInvoice::whereDate('invoice_date', '>=', $startOfMonth)->whereDate('invoice_date', '<=', $endOfMonth)->sum('grand_total');
        // By job type (this month)
        $revenueByJobType = JobCard::whereDate('job_date', '>=', $startOfMonth)->whereDate('job_date', '<=', $endOfMonth)->where('status', 'delivered')->selectRaw('job_type, SUM(grand_total) as total')->groupBy('job_type')->orderByDesc('total')->get();
        // By vehicle brand (this month) — EV-specific: Ola, Ather, TVS, Bajaj, Hero Vida, etc.
        $revenueByVehicleBrand = JobCard::whereDate('job_cards.job_date', '>=', $startOfMonth)->whereDate('job_cards.job_date', '<=', $endOfMonth)->where('job_cards.status', 'delivered')
            ->join('vehicles', 'vehicles.id', '=', 'job_cards.vehicle_id')
            ->join('vehicle_models', 'vehicle_models.id', '=', 'vehicles.vehicle_model_id')
            ->join('vehicle_brands', 'vehicle_brands.id', '=', 'vehicle_models.vehicle_brand_id')
            ->selectRaw('vehicle_brands.name as brand, SUM(job_cards.grand_total) as total, COUNT(*) as count')
            ->groupBy('vehicle_brands.name')
            ->orderByDesc('total')
            ->get();
        // Payment mode breakdown (this month)
        $paymentsThisMonth = Payment::whereDate('payment_date', '>=', $startOfMonth)->whereDate('payment_date', '<=', $endOfMonth)->get();
        $modeOrder = array_keys(config('garage.option_lists.payment_mode'));
        $paymentModeBreakdown = collect($modeOrder)->mapWithKeys(fn (string $mode) => [$mode => ['count' => $paymentsThisMonth->where('payment_mode', $mode)->count(), 'total' => round($paymentsThisMonth->where('payment_mode', $mode)->sum('amount'), 2)]])->filter(fn ($v) => $v['count'] > 0);

        // ── Operations ──
        $statusCounts = JobCard::selectRaw('status, COUNT(*) as cnt')->groupBy('status')->pluck('cnt', 'status');
        $statusDistribution = collect(config('garage.option_lists.job_card_status'))->map(fn ($label, $key) => ['status' => $key, 'label' => $label, 'count' => (int) ($statusCounts[$key] ?? 0)])->values();
        // Avg turnaround for delivered jobs (job_date → updated_at)
        $avgTurnaroundDays = (float) (JobCard::where('status', 'delivered')->selectRaw('AVG(julianday(updated_at) - julianday(job_date)) as avg_days')->value('avg_days') ?? 0);
        $avgTurnaroundDays = round($avgTurnaroundDays, 1);

        // ── Money owed — aging buckets 0-7 / 8-30 / 30+ days ──
        $outstandingDocs = collect();
        JobCard::whereNotNull('grand_total')->where('grand_total', '>', 0)->withSum('payments', 'amount')->get()->each(function (JobCard $jc) use (&$outstandingDocs) {
            $out = max(0, (float) $jc->grand_total - (float) ($jc->payments_sum_amount ?? 0));
            if ($out > 0.005) {
                $outstandingDocs->push(['date' => $jc->job_date, 'outstanding' => $out]);
            }
        });
        CounterInvoice::whereNotNull('grand_total')->where('grand_total', '>', 0)->withSum('payments', 'amount')->get()->each(function (CounterInvoice $ci) use (&$outstandingDocs) {
            $out = max(0, (float) $ci->grand_total - (float) ($ci->payments_sum_amount ?? 0));
            if ($out > 0.005) {
                $outstandingDocs->push(['date' => $ci->invoice_date, 'outstanding' => $out]);
            }
        });
        $agingBuckets = ['0-7' => 0, '8-30' => 0, '30+' => 0];
        foreach ($outstandingDocs as $doc) {
            $age = Carbon::parse($doc['date'])->diffInDays($today);
            $amt = $doc['outstanding'];
            if ($age <= 7) {
                $agingBuckets['0-7'] += $amt;
            } elseif ($age <= 30) {
                $agingBuckets['8-30'] += $amt;
            } else {
                $agingBuckets['30+'] += $amt;
            }
        }
        $agingBuckets = collect($agingBuckets)->map(fn ($v) => round($v, 2));

        // ── Customers ──
        // Top 5 by paid (existing logic)
        $topCustomersBySpend = collect();
        // Reuse customers report logic for top by spend: sum payments of docs in last 30 days
        $custFrom = $today->copy()->subDays(29)->toDateString();
        $jobCardsForSpend = JobCard::with('customer')->whereDate('job_date', '>=', $custFrom)->whereDate('job_date', '<=', $endOfMonth)->get(['id', 'customer_id']);
        $invForSpend = CounterInvoice::with('customer')->whereDate('invoice_date', '>=', $custFrom)->whereDate('invoice_date', '<=', $endOfMonth)->get(['id', 'customer_id']);
        $jcPay = $this->paymentTotalsFor(JobCard::class, $jobCardsForSpend->pluck('id'));
        $invPay = $this->paymentTotalsFor(CounterInvoice::class, $invForSpend->pluck('id'));
        $customerSpend = [];
        foreach ($jobCardsForSpend as $jc) {
            $this->accumulateCustomer($customerSpend, $jc->customer_id, $jc->customer, (float) ($jcPay[$jc->id] ?? 0));
        }
        foreach ($invForSpend as $inv) {
            $this->accumulateCustomer($customerSpend, $inv->customer_id, $inv->customer, (float) ($invPay[$inv->id] ?? 0));
        }
        $topCustomersBySpend = collect($customerSpend)->map(fn ($r) => ['customer' => $r['customer'], 'total_paid' => round($r['total_paid'], 2), 'visits' => $r['visits']])->sortByDesc('total_paid')->take(5)->values();
        // New vs returning (last 6 months)
        $newVsReturning = $trendMonths->map(function (Carbon $m) {
            $s = $m->copy()->startOfMonth()->toDateString();
            $e = $m->copy()->endOfMonth()->toDateString();
            $new = Customer::whereDate('created_at', '>=', $s)->whereDate('created_at', '<=', $e)->count();
            $activeIds = JobCard::whereDate('job_date', '>=', $s)->whereDate('job_date', '<=', $e)->pluck('customer_id')->merge(CounterInvoice::whereDate('invoice_date', '>=', $s)->whereDate('invoice_date', '<=', $e)->whereNotNull('customer_id')->pluck('customer_id'))->unique()->filter();
            $returning = $activeIds->filter(fn ($id) => Customer::whereKey($id)->value('created_at') < $s)->count();

            // Ensure new not double-counted in returning
            return ['label' => $m->format('M y'), 'new' => $new, 'returning' => $returning];
        });
        // ── Technicians ──
        $technicianStats = JobCard::where('status', 'delivered')->whereNotNull('assigned_technician_id')->selectRaw('assigned_technician_id, COUNT(*) as jobs, SUM(grand_total) as revenue')->groupBy('assigned_technician_id')->with('assignedTechnician')->get()->map(fn ($row) => ['technician' => $row->assignedTechnician, 'jobs' => (int) $row->jobs, 'revenue' => round((float) $row->revenue, 2)])->sortByDesc('revenue')->values();

        // ── Inventory ──
        $lowStockParts = SparePart::whereColumn('current_stock_qty', '<=', 'reorder_level')->orderBy('part_name')->limit(5)->get(['part_name', 'part_code', 'current_stock_qty', 'reorder_level']);
        $lowStockCount = SparePart::whereColumn('current_stock_qty', '<=', 'reorder_level')->count();
        // Fast vs slow movers (last 30 days usage)
        $jcUsage = DB::table('job_card_parts')->join('job_cards', 'job_cards.id', '=', 'job_card_parts.job_card_id')->whereDate('job_cards.job_date', '>=', $dailyFrom)->whereDate('job_cards.job_date', '<=', $endOfMonth)->select('spare_part_id', DB::raw('SUM(quantity) as c'))->groupBy('spare_part_id')->pluck('c', 'spare_part_id');
        $ciUsage = DB::table('counter_invoice_items')->join('counter_invoices', 'counter_invoices.id', '=', 'counter_invoice_items.counter_invoice_id')->whereDate('counter_invoices.invoice_date', '>=', $dailyFrom)->whereDate('counter_invoices.invoice_date', '<=', $endOfMonth)->select('spare_part_id', DB::raw('SUM(quantity) as c'))->groupBy('spare_part_id')->pluck('c', 'spare_part_id');
        $partsUsage = SparePart::select(['id', 'part_name', 'part_code', 'current_stock_qty'])->get()->map(fn ($p) => ['part' => $p, 'used' => (int) ($jcUsage[$p->id] ?? 0) + (int) ($ciUsage[$p->id] ?? 0)])->sortByDesc('used');
        $fastMoving = $partsUsage->take(5)->values();
        $slowMoving = $partsUsage->filter(fn ($x) => $x['used'] > 0)->sortBy('used')->take(5)->values();

        // Module counts for header
        $totalCustomers = Customer::count();
        $totalVehicles = DB::table('vehicles')->count();
        $totalEmployees = Employee::count();

        return view('analytics.index', compact(
            'totalOutstanding', 'openJobCardsCount', 'revenueThisMonth',
            'revenueDailyTrend', 'revenueWeeklyTrend', 'revenueMonthlyTrend', 'jobCardsRevenueMonth', 'counterRevenueMonth', 'revenueByJobType', 'revenueByVehicleBrand', 'avgJobValueTrend', 'paymentModeBreakdown',
            'statusDistribution', 'avgTurnaroundDays',
            'agingBuckets',
            'topCustomersBySpend', 'newVsReturning',
            'technicianStats',
            'lowStockParts', 'lowStockCount', 'fastMoving', 'slowMoving',
            'totalCustomers', 'totalVehicles', 'totalEmployees'
        ));
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

        $inventory = SparePart::query()
            ->select(['id', 'part_name', 'part_code', 'category', 'unit', 'purchase_price', 'mrp', 'labor_charge', 'current_stock_qty', 'reorder_level'])
            ->orderBy('part_name')
            ->get()
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

        return view('analytics.customers', compact('fromDate', 'toDate', 'customers'));
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

        // The revenue report builds a row per day in the range in PHP; cap the
        // span so an absurd range cannot turn one request into thousands of
        // loop iterations.
        if (Carbon::parse($fromDate)->diffInDays(Carbon::parse($toDate)) > 366) {
            throw ValidationException::withMessages([
                'to_date' => 'The report range cannot exceed 366 days.',
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
