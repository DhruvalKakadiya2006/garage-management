<?php

namespace App\Http\Controllers;

use App\Models\CounterInvoice;
use App\Models\JobCard;
use App\Models\Payment;
use App\Models\SparePart;
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

        // This month's revenue (same query logic as ReportController@revenue).
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

        // Outstanding dues across Job Cards + Counter Invoices (derived live).
        $totalBilled = JobCard::sum('grand_total') + CounterInvoice::sum('grand_total');
        $totalPaid = Payment::sum('amount');
        $outstandingDues = max(0, $totalBilled - $totalPaid);

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
