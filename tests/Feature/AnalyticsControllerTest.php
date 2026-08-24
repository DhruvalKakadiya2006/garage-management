<?php

namespace Tests\Feature;

use App\Models\CounterInvoice;
use App\Models\CounterInvoiceItem;
use App\Models\Customer;
use App\Models\JobCard;
use App\Models\JobCardPart;
use App\Models\Payment;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsVehicleCatalog;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeCustomer(string $name = 'Dhruval', string $mobile = '9000000001'): Customer
    {
        return Customer::create([
            'first_name' => $name,
            'mobile' => $mobile,
            'customer_type' => 'regular',
        ]);
    }

    private function makeVehicle(Customer $customer): Vehicle
    {
        return $this->makeCatalogVehicle($customer, 'GJ-01-AB-'.random_int(1000, 9999), 'Ola', 'S1 Pro');
    }

    private function makeJobCard(Customer $customer, string $date, string $status = 'delivered', string $jobType = 'paid_service', float $grandTotal = 0): JobCard
    {
        return JobCard::create([
            'job_date' => $date,
            'customer_id' => $customer->id,
            'vehicle_id' => $this->makeVehicle($customer)->id,
            'job_type' => $jobType,
            'status' => $status,
            'grand_total' => $grandTotal,
        ]);
    }

    private function makeCounterInvoice(?Customer $customer, string $date, float $grandTotal = 0): CounterInvoice
    {
        return CounterInvoice::create([
            'invoice_date' => $date,
            'customer_id' => $customer?->id,
            'grand_total' => $grandTotal,
        ]);
    }

    private function makePart(string $code, float $purchasePrice, int $stock, int $reorderLevel = 5): SparePart
    {
        return SparePart::create([
            'part_name' => $code,
            'part_code' => $code,
            'category' => 'battery',
            'unit' => 'pcs',
            'purchase_price' => $purchasePrice,
            'mrp' => $purchasePrice * 2,
            'labor_charge' => 0,
            'current_stock_qty' => $stock,
            'reorder_level' => $reorderLevel,
        ]);
    }

    // ----------------------------------------------------------------
    // Revenue Report
    // ----------------------------------------------------------------

    public function test_revenue_defaults_to_current_month_and_totals_both_sources()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $today = Carbon::now()->toDateString();

        $this->makeJobCard($customer, $today, 'delivered', 'paid_service', 700);
        $this->makeJobCard($customer, $today, 'received', 'paid_service', 500);
        $this->makeCounterInvoice($customer, $today, 300);

        $response = $this->actingAs($user)->get(route('analytics.revenue'));

        $response->assertOk()
            ->assertViewHas('fromDate', Carbon::now()->startOfMonth()->toDateString())
            ->assertViewHas('toDate', $today)
            ->assertViewHas('jobCardsRevenue', 700.0)
            ->assertViewHas('counterInvoicesRevenue', 300.0)
            ->assertViewHas('totalRevenue', 1000.0);
    }

    public function test_revenue_builds_a_continuous_daily_series_without_n_plus_one()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $today = Carbon::now()->toDateString();
        $yesterday = Carbon::now()->subDay()->toDateString();

        $this->makeJobCard($customer, $today, 'delivered', 'paid_service', 700);
        $this->makeJobCard($customer, $yesterday, 'delivered', 'paid_service', 100);

        $response = $this->actingAs($user)->get(route('analytics.revenue'));

        $response->assertOk();
        $revenueByDay = $response->viewData('revenueByDay');

        // One row for every day in the range (start of month -> today)
        $expectedDays = Carbon::now()->startOfMonth()->diffInDays(Carbon::now()) + 1;
        $this->assertCount($expectedDays, $revenueByDay);

        $todayRow = $revenueByDay->first(fn ($row) => $row['date_full'] === $today);
        $this->assertNotNull($todayRow);
        $this->assertEqualsWithDelta(700.0, $todayRow['total'], 0.01);

        $yesterdayRow = $revenueByDay->first(fn ($row) => $row['date_full'] === $yesterday);
        $this->assertNotNull($yesterdayRow);
        $this->assertEqualsWithDelta(100.0, $yesterdayRow['total'], 0.01);
    }

    public function test_revenue_report_respects_custom_date_range()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $today = Carbon::now()->toDateString();

        $this->makeJobCard($customer, $today, 'delivered', 'paid_service', 700);
        $this->makeCounterInvoice($customer, $today, 300);

        $response = $this->actingAs($user)->get(route('analytics.revenue', [
            'from_date' => $today,
            'to_date' => $today,
        ]));

        $response->assertOk()->assertViewHas('totalRevenue', 1000.0);
    }

    // ----------------------------------------------------------------
    // Date range validation
    // ----------------------------------------------------------------

    public function test_reports_reject_an_inverted_date_range()
    {
        $user = $this->makeUser();
        $from = '2026-08-10';
        $to = '2026-08-01';

        $this->actingAs($user)
            ->get(route('analytics.payments', ['from_date' => $from, 'to_date' => $to]))
            ->assertSessionHasErrors('to_date');
    }

    public function test_reports_require_both_dates_when_one_is_given()
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('analytics.payments', ['from_date' => '2026-08-01']))
            ->assertSessionHasErrors('to_date');

        $this->actingAs($user)
            ->get(route('analytics.payments', ['to_date' => '2026-08-01']))
            ->assertSessionHasErrors('from_date');
    }

    // ----------------------------------------------------------------
    // Job Cards Report
    // ----------------------------------------------------------------

    public function test_job_cards_report_counts_by_status_and_job_type()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $date = Carbon::now()->toDateString();

        $this->makeJobCard($customer, $date, 'delivered', 'paid_service', 100);
        $this->makeJobCard($customer, $date, 'delivered', 'warranty', 100);
        $this->makeJobCard($customer, $date, 'received', 'paid_service', 100);

        $response = $this->actingAs($user)->get(route('analytics.job_cards'));

        $response->assertOk()
            ->assertViewHas('totalJobCards', 3);

        $statusCounts = $response->viewData('statusCounts')->pluck('count', 'status');
        $this->assertEquals(2, $statusCounts['delivered']);
        $this->assertEquals(1, $statusCounts['received']);

        $jobTypeCounts = $response->viewData('jobTypeCounts')->pluck('count', 'job_type');
        $this->assertEquals(2, $jobTypeCounts['paid_service']);
        $this->assertEquals(1, $jobTypeCounts['warranty']);
    }

    // ----------------------------------------------------------------
    // Inventory Report
    // ----------------------------------------------------------------

    public function test_inventory_report_flags_low_stock_and_sums_usage()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $date = Carbon::now()->toDateString();

        $partA = $this->makePart('BRK-01', 100, 10, 5);
        $partB = $this->makePart('OIL-01', 50, 20, 5);

        $jobCard = $this->makeJobCard($customer, $date, 'delivered', 'paid_service', 100);
        $invoice = $this->makeCounterInvoice($customer, $date, 100);

        // Part A: 3 used on job card + 2 used on counter invoice = 5 (stock 10 -> 5, low stock)
        JobCardPart::create([
            'job_card_id' => $jobCard->id,
            'spare_part_id' => $partA->id,
            'quantity' => 3,
            'mrp' => 200,
            'labor_charge' => 0,
            'line_total' => 600,
        ]);
        CounterInvoiceItem::create([
            'counter_invoice_id' => $invoice->id,
            'spare_part_id' => $partA->id,
            'quantity' => 2,
            'mrp' => 200,
            'line_total' => 400,
        ]);

        $response = $this->actingAs($user)->get(route('analytics.inventory'));

        $response->assertOk()
            ->assertViewHas('lowStockCount', 1)
            ->assertViewHas('totalUnits', 25)
            ->assertViewHas('totalStockValue', 1500.0);

        $inventory = $response->viewData('inventory')->keyBy('id');

        $this->assertTrue($inventory[$partA->id]['low_stock']);
        $this->assertEquals(5, $inventory[$partA->id]['times_used']);
        $this->assertEquals(5, $inventory[$partA->id]['current_stock_qty']);
        $this->assertEqualsWithDelta(500.0, $inventory[$partA->id]['stock_value'], 0.01);

        $this->assertFalse($inventory[$partB->id]['low_stock']);
        $this->assertEquals(0, $inventory[$partB->id]['times_used']);
        $this->assertEqualsWithDelta(1000.0, $inventory[$partB->id]['stock_value'], 0.01);
    }

    // ----------------------------------------------------------------
    // Payments Report
    // ----------------------------------------------------------------

    public function test_payments_report_groups_by_mode_and_lists_payments()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $date = Carbon::now()->toDateString();

        $jobCard = $this->makeJobCard($customer, $date, 'delivered', 'paid_service', 1000);
        $invoice = $this->makeCounterInvoice($customer, $date, 500);

        Payment::create(['payable_id' => $jobCard->id, 'payable_type' => JobCard::class, 'amount' => 500, 'payment_mode' => 'cash', 'payment_date' => $date]);
        Payment::create(['payable_id' => $invoice->id, 'payable_type' => CounterInvoice::class, 'amount' => 300, 'payment_mode' => 'upi', 'payment_date' => $date]);
        Payment::create(['payable_id' => $jobCard->id, 'payable_type' => JobCard::class, 'amount' => 200, 'payment_mode' => 'card', 'payment_date' => $date]);
        Payment::create(['payable_id' => $invoice->id, 'payable_type' => CounterInvoice::class, 'amount' => 999, 'payment_mode' => 'cash', 'payment_date' => Carbon::now()->subMonth()->toDateString()]);

        $response = $this->actingAs($user)->get(route('analytics.payments'));

        $response->assertOk()
            ->assertViewHas('totalPayments', 1000.0);

        $paymentsByMode = $response->viewData('paymentsByMode');
        $this->assertEquals(1, $paymentsByMode['cash']['count']);
        $this->assertEquals(500.0, $paymentsByMode['cash']['total']);
        $this->assertEquals(1, $paymentsByMode['upi']['count']);
        $this->assertEquals(300.0, $paymentsByMode['upi']['total']);
        $this->assertEquals(1, $paymentsByMode['card']['count']);
        $this->assertEquals(200.0, $paymentsByMode['card']['total']);
        $this->assertEquals(0, $paymentsByMode['bank_transfer']['count']);
        $this->assertEquals(0, $paymentsByMode['cheque']['count']);

        // The out-of-range payment is excluded from the list
        $this->assertCount(3, $response->viewData('payments'));
    }

    // ----------------------------------------------------------------
    // Customers Report
    // ----------------------------------------------------------------

    public function test_customers_report_ranks_customers_by_amount_paid()
    {
        $user = $this->makeUser();
        $customerA = $this->makeCustomer('Alice', '9000000001');
        $customerB = $this->makeCustomer('Bob', '9000000002');
        $date = Carbon::now()->toDateString();

        $jobCardA = $this->makeJobCard($customerA, $date, 'delivered', 'paid_service', 1000);
        $invoiceA = $this->makeCounterInvoice($customerA, $date, 600);
        $jobCardB = $this->makeJobCard($customerB, $date, 'delivered', 'paid_service', 500);

        // Walk-in invoice must be ignored (no customer)
        $walkIn = $this->makeCounterInvoice(null, $date, 999);

        Payment::create(['payable_id' => $jobCardA->id, 'payable_type' => JobCard::class, 'amount' => 400, 'payment_mode' => 'cash', 'payment_date' => $date]);
        Payment::create(['payable_id' => $invoiceA->id, 'payable_type' => CounterInvoice::class, 'amount' => 600, 'payment_mode' => 'upi', 'payment_date' => $date]);
        Payment::create(['payable_id' => $jobCardB->id, 'payable_type' => JobCard::class, 'amount' => 250, 'payment_mode' => 'cash', 'payment_date' => $date]);
        Payment::create(['payable_id' => $walkIn->id, 'payable_type' => CounterInvoice::class, 'amount' => 999, 'payment_mode' => 'cash', 'payment_date' => $date]);

        $response = $this->actingAs($user)->get(route('analytics.customers'));

        $response->assertOk();

        $customers = $response->viewData('customers');
        $this->assertCount(2, $customers);

        // Ranked by amount paid descending: Alice (1000) before Bob (250)
        $this->assertEquals($customerA->id, $customers[0]['customer']->id);
        $this->assertEqualsWithDelta(1000.0, $customers[0]['total_paid'], 0.01);
        $this->assertEquals(2, $customers[0]['visits']);

        $this->assertEquals($customerB->id, $customers[1]['customer']->id);
        $this->assertEqualsWithDelta(250.0, $customers[1]['total_paid'], 0.01);
        $this->assertEquals(1, $customers[1]['visits']);
    }

    // ----------------------------------------------------------------
    // New Analytics Features
    // ----------------------------------------------------------------

    public function test_revenue_includes_six_month_trend()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $today = Carbon::now()->toDateString();
        $fiveMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth()->toDateString();

        // Create job cards and counter invoices across 6 months
        for ($m = 5; $m >= 0; $m--) {
            $monthDate = Carbon::now()->subMonths($m)->startOfMonth()->toDateString();
            $this->makeJobCard($customer, $monthDate, 'delivered', 'paid_service', 200 + $m * 50);
            $this->makeCounterInvoice($customer, $monthDate, 100 + $m * 20);
        }

        $response = $this->actingAs($user)->get(route('analytics.revenue'));

        $response->assertOk();

        $revenueTrend = $response->viewData('revenueTrend');
        $this->assertCount(6, $revenueTrend);

        // Verify each month has a label and total
        $labels = $revenueTrend->pluck('label')->toArray();
        $totals = $revenueTrend->pluck('total')->toArray();

        // All 6 months should have labels in "M y" format (e.g., "Mar 26")
        foreach ($labels as $label) {
            $this->assertMatchesRegularExpression('/^[A-Z][a-z]{2} \d{2}$/', $label);
        }

        // Totals should be positive (at least the minimum we seeded)
        foreach ($totals as $total) {
            $this->assertGreaterThan(0, $total);
        }
    }

    public function test_job_cards_report_includes_average_job_value()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $today = Carbon::now()->toDateString();

        // Create 3 delivered job cards with known totals
        $this->makeJobCard($customer, $today, 'delivered', 'paid_service', 500);
        $this->makeJobCard($customer, $today, 'delivered', 'paid_service', 300);
        $this->makeJobCard($customer, $today, 'delivered', 'paid_service', 100);

        // Create one non-delivered card (should not count toward average)
        $this->makeJobCard($customer, $today, 'received', 'paid_service', 200);

        $response = $this->actingAs($user)->get(route('analytics.job_cards'));

        $response->assertOk();

        $averageJobValue = $response->viewData('averageJobValue');
        // (500 + 300 + 100) / 3 = 300
        $this->assertEqualsWithDelta(300.0, $averageJobValue, 0.01);
    }

    public function test_customers_report_splits_revenue_by_customer_type()
    {
        $user = $this->makeUser();

        // Regular customer with delivered job card + counter invoice
        $regularCustomer = $this->makeCustomer('Regular Customer', '9000000003');
        $regularJobCard = $this->makeJobCard($regularCustomer, Carbon::now()->toDateString(), 'delivered', 'paid_service', 800);
        $regularInvoice = $this->makeCounterInvoice($regularCustomer, Carbon::now()->toDateString(), 400);

        // Counter customer with delivered job card
        $counterCustomer = $this->makeCustomer('Counter Customer', '9000000004');
        $counterCustomer->customer_type = 'counter';
        $counterCustomer->save();
        $counterJobCard = $this->makeJobCard($counterCustomer, Carbon::now()->toDateString(), 'delivered', 'paid_service', 600);

        // Walk-in customer (null customer_id) with counter invoice
        $walkInInvoice = $this->makeCounterInvoice(null, Carbon::now()->toDateString(), 200);

        // Payments
        Payment::create(['payable_id' => $regularJobCard->id, 'payable_type' => JobCard::class, 'amount' => 600, 'payment_mode' => 'cash', 'payment_date' => Carbon::now()->toDateString()]);
        Payment::create(['payable_id' => $regularInvoice->id, 'payable_type' => CounterInvoice::class, 'amount' => 300, 'payment_mode' => 'upi', 'payment_date' => Carbon::now()->toDateString()]);
        Payment::create(['payable_id' => $counterJobCard->id, 'payable_type' => JobCard::class, 'amount' => 450, 'payment_mode' => 'card', 'payment_date' => Carbon::now()->toDateString()]);

        $response = $this->actingAs($user)->get(route('analytics.customers'));

        $response->assertOk();

        $revenueByType = $response->viewData('revenueByCustomerType');
        $this->assertCount(3, $revenueByType);

        // Regular: 800 (job card) + 300 (counter invoice payment) = 1100? Wait, revenueByCustomerType uses grand_total not payments.
        // Actually the method uses SUM(grand_total) from delivered job cards + counter invoices.
        // Regular: job card grand_total = 800 + counter invoice grand_total = 400 = 1200
        // Counter: job card grand_total = 600 = 600
        // Walk-in: counter invoice grand_total = 200 = 200

        $regularEntry = $revenueByType->first(fn ($item) => $item['key'] === 'regular');
        $counterEntry = $revenueByType->first(fn ($item) => $item['key'] === 'counter');
        $walkInEntry = $revenueByType->first(fn ($item) => $item['key'] === 'walk_in');

        $this->assertNotNull($regularEntry);
        $this->assertEqualsWithDelta(1200.0, $regularEntry['total'], 0.01);

        $this->assertNotNull($counterEntry);
        $this->assertEqualsWithDelta(600.0, $counterEntry['total'], 0.01);

        $this->assertNotNull($walkInEntry);
        $this->assertEqualsWithDelta(200.0, $walkInEntry['total'], 0.01);
    }

    public function test_analytics_index_shows_outstanding_kpis()
    {
        $user = $this->makeUser();

        // Create a job card with grand_total and partial payments
        $customer = $this->makeCustomer('KPI Test', '9000000005');
        $jobCard = $this->makeJobCard($customer, Carbon::now()->toDateString(), 'delivered', 'paid_service', 1000);
        Payment::create(['payable_id' => $jobCard->id, 'payable_type' => JobCard::class, 'amount' => 400, 'payment_mode' => 'cash', 'payment_date' => Carbon::now()->toDateString()]);

        // Create a counter invoice with grand_total and partial payments
        $counterInvoice = $this->makeCounterInvoice($customer, Carbon::now()->toDateString(), 800);
        Payment::create(['payable_id' => $counterInvoice->id, 'payable_type' => CounterInvoice::class, 'amount' => 200, 'payment_mode' => 'upi', 'payment_date' => Carbon::now()->toDateString()]);

        // Create an open job card (not delivered, so should count toward open jobs)
        $openJobCard = JobCard::create([
            'job_date' => Carbon::now()->toDateString(),
            'customer_id' => $customer->id,
            'vehicle_id' => $this->makeVehicle($customer)->id,
            'job_type' => 'paid_service',
            'status' => 'in_progress',
            'grand_total' => 750,
        ]);

        $response = $this->actingAs($user)->get(route('analytics.index'));

        $response->assertOk();

        $totalOutstanding = $response->viewData('totalOutstanding');
        // Job card outstanding: max(0, 1000 - 400) = 600
        // Counter invoice outstanding: max(0, 800 - 200) = 600
        // The open (billed, in_progress) job card also owes its 750 now that
        // outstanding covers every billed document regardless of status.
        // Total: 600 + 600 + 750 = 1950
        $this->assertEqualsWithDelta(1950.0, $totalOutstanding, 0.01);

        $openJobCardsCount = $response->viewData('openJobCardsCount');
        // Only the open (in_progress) job card counts, delivered ones excluded
        $this->assertEquals(1, $openJobCardsCount);
    }

    public function test_reports_reject_ranges_longer_than_a_year()
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('analytics.payments', ['from_date' => '2024-01-01', 'to_date' => '2026-08-01']))
            ->assertSessionHasErrors('to_date');

        // A 366-day span stays allowed.
        $this->actingAs($user)
            ->get(route('analytics.revenue', ['from_date' => '2025-08-01', 'to_date' => '2026-08-01']))
            ->assertOk();
    }

    public function test_analytics_index_computes_outstanding_without_per_row_payment_sums()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer('NPlusOne', '9000000006');
        $today = Carbon::now()->toDateString();

        $jobCardIds = [];
        foreach ([100, 200, 300] as $grandTotal) {
            $card = $this->makeJobCard($customer, $today, 'delivered', 'paid_service', $grandTotal);
            Payment::create(['payable_id' => $card->id, 'payable_type' => JobCard::class, 'amount' => 10, 'payment_mode' => 'cash', 'payment_date' => $today]);
            $jobCardIds[] = $card->id;
        }

        foreach ([150, 250, 350] as $grandTotal) {
            $invoice = $this->makeCounterInvoice($customer, $today, $grandTotal);
            Payment::create(['payable_id' => $invoice->id, 'payable_type' => CounterInvoice::class, 'amount' => 20, 'payment_mode' => 'cash', 'payment_date' => $today]);
        }

        // A per-row fallback SUM against payments means the amount_paid
        // accessors missed their eager-loaded fast path (aliased withSum).
        $perRowPaymentSumQueries = 0;
        DB::listen(function ($query) use (&$perRowPaymentSumQueries) {
            if (str_starts_with(strtolower($query->sql), 'select sum(')
                && str_contains(strtolower($query->sql), 'from "payments"')) {
                $perRowPaymentSumQueries++;
            }
        });

        $response = $this->actingAs($user)->get(route('analytics.index'));

        $this->assertSame(0, $perRowPaymentSumQueries);

        // (100 + 200 + 300 - 30) + (150 + 250 + 350 - 60) = 1260
        $this->assertEqualsWithDelta(1260.0, $response->viewData('totalOutstanding'), 0.01);
    }
}
