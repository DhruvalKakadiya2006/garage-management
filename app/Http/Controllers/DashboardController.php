<?php

namespace App\Http\Controllers;

use App\Models\CounterInvoice;
use App\Models\JobCard;
use App\Models\SparePart;
use App\Services\OutstandingBalanceService;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Show the dashboard bento-grid overview.
     */
    public function index()
    {
        // Today's job cards grouped by status.
        $todayJobCards = JobCard::whereDate('job_date', Carbon::today())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $todayJobCardsTotal = $todayJobCards->sum();

        // This month's revenue (same query logic as AnalyticsController@revenue).
        $fromDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $toDate = Carbon::now()->format('Y-m-d');

        $monthRevenue = JobCard::whereDate('job_date', '>=', $fromDate)
            ->whereDate('job_date', '<=', $toDate)
            ->where('status', 'delivered')
            ->sum('grand_total')
            + CounterInvoice::whereDate('invoice_date', '>=', $fromDate)
                ->whereDate('invoice_date', '<=', $toDate)
                ->sum('grand_total');

        // Low stock alert: parts at or below their reorder level.
        $lowStockCount = SparePart::whereColumn('current_stock_qty', '<=', 'reorder_level')
            ->count();

        // Outstanding dues across Job Cards + Counter Invoices — the shared
        // clamped per-document definition (an overpayment on one document can
        // never offset another document's unpaid balance).
        $outstandingDues = app(OutstandingBalanceService::class)->total();

        // Five most recent job cards.
        $recentJobCards = JobCard::with('customer')->latest()->limit(5)->get();

        return view('dashboard', compact(
            'todayJobCards',
            'todayJobCardsTotal',
            'monthRevenue',
            'lowStockCount',
            'outstandingDues',
            'recentJobCards'
        ));
    }
}
