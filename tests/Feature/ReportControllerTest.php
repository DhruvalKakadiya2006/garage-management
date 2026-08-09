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
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class ReportControllerTest extends TestCase
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
        return $this->makeCatalogVehicle($customer, 'GJ-01-AB-' . random_int(1000, 9999), 'Ola', 'S1 Pro');
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

        $response = $this->actingAs($user)->get(route('reports.revenue'));

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

        $response = $this->actingAs($user)->get(route('reports.revenue'));

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

        $response = $this->actingAs($user)->get(route('reports.revenue', [
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
            ->get(route('reports.payments', ['from_date' => $from, 'to_date' => $to]))
            ->assertSessionHasErrors('to_date');
    }

    public function test_reports_require_both_dates_when_one_is_given()
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('reports.payments', ['from_date' => '2026-08-01']))
            ->assertSessionHasErrors('to_date');

        $this->actingAs($user)
            ->get(route('reports.payments', ['to_date' => '2026-08-01']))
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

        $response = $this->actingAs($user)->get(route('reports.job_cards'));

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

        $response = $this->actingAs($user)->get(route('reports.inventory'));

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

        $response = $this->actingAs($user)->get(route('reports.payments'));

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

        $response = $this->actingAs($user)->get(route('reports.customers'));

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
}
