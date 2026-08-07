<?php

namespace Tests\Feature;

use App\Models\CounterInvoice;
use App\Models\Customer;
use App\Models\JobCard;
use App\Models\Payment;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makePart(string $code, string $name, float $mrp, int $stock): SparePart
    {
        return SparePart::create([
            'part_name' => $name,
            'part_code' => $code,
            'category' => 'battery',
            'unit' => 'pcs',
            'purchase_price' => $mrp * 0.6,
            'mrp' => $mrp,
            'labor_charge' => 0,
            'gst_rate' => 18,
            'current_stock_qty' => $stock,
            'reorder_level' => 5,
        ]);
    }

    private function makePayment(int $payableId, string $payableType): Payment
    {
        return Payment::create([
            'payable_id' => $payableId,
            'payable_type' => $payableType,
            'amount' => 100,
            'payment_mode' => 'cash',
            'payment_date' => '2026-08-03',
            'reference_no' => 'REF-1',
            'notes' => 'Test payment with a moderately long note for the list view.',
        ]);
    }

    public function test_deleting_job_card_deletes_its_payments()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = Vehicle::create(['customer_id' => $customer->id, 'registration_no' => 'GJ-01-AB-1234', 'vehicle_brand' => 'Ola', 'model' => 'S1 Pro']);

        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
            'grand_total' => 500,
        ]);

        $this->makePayment($jobCard->id, JobCard::class);
        $this->assertSame(1, Payment::where('payable_type', JobCard::class)->where('payable_id', $jobCard->id)->count());

        $this->actingAs($user)
            ->delete(route('job-cards.destroy', $jobCard))
            ->assertRedirect(route('job-cards.index'));

        $this->assertDatabaseMissing('job_cards', ['id' => $jobCard->id]);
        $this->assertSame(0, Payment::where('payable_type', JobCard::class)->where('payable_id', $jobCard->id)->count());
    }

    public function test_deleting_counter_invoice_deletes_its_payments()
    {
        $user = $this->makeUser();
        $part = $this->makePart('BRK-01', 'Brake Shoe', 50, 20);

        $invoice = CounterInvoice::create([
            'invoice_date' => '2026-08-03',
            'gst_applicable' => false,
            'grand_total' => 300,
        ]);
        $invoice->items()->create([
            'spare_part_id' => $part->id,
            'quantity' => 2,
            'mrp' => 50,
            'discount_percent' => 0,
            'line_total' => 100,
        ]);

        $this->makePayment($invoice->id, CounterInvoice::class);
        $this->assertSame(1, Payment::where('payable_type', CounterInvoice::class)->where('payable_id', $invoice->id)->count());

        $this->actingAs($user)
            ->delete(route('counter-invoices.destroy', $invoice))
            ->assertRedirect(route('counter-invoices.index'));

        $this->assertDatabaseMissing('counter_invoices', ['id' => $invoice->id]);
        $this->assertSame(0, Payment::where('payable_type', CounterInvoice::class)->where('payable_id', $invoice->id)->count());
    }

    public function test_payments_index_renders_deleted_record_for_orphaned_payment()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = Vehicle::create(['customer_id' => $customer->id, 'registration_no' => 'GJ-01-AB-1234', 'vehicle_brand' => 'Ola', 'model' => 'S1 Pro']);

        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
            'grand_total' => 500,
        ]);

        // Normal payment with live parent renders the document link + customer.
        $this->makePayment($jobCard->id, JobCard::class);
        // Orphaned payment (parent already deleted) must not crash the page.
        $this->makePayment(999999, JobCard::class);

        $this->actingAs($user)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Deleted record')
            ->assertSee('View Document')
            ->assertSee('Dhruval')
            ->assertSee('Test payment with a moderately');
    }

    public function test_index_pages_eager_load_payment_sum_and_derive_status_labels()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = Vehicle::create(['customer_id' => $customer->id, 'registration_no' => 'GJ-01-AB-1234', 'vehicle_brand' => 'Ola', 'model' => 'S1 Pro']);

        $paidJob = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
            'grand_total' => 500,
        ]);
        $unpaidJob = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
            'grand_total' => 300,
        ]);
        $invoice = CounterInvoice::create([
            'invoice_date' => '2026-08-03',
            'gst_applicable' => false,
            'grand_total' => 300,
        ]);
        $this->makePayment($paidJob->id, JobCard::class);
        $this->makePayment($invoice->id, CounterInvoice::class);

        $response = $this->actingAs($user)->get(route('job-cards.index'));
        $response->assertOk()
            ->assertSee('Partially Paid')
            ->assertSee('Unpaid');

        // Every list model carries the eager-loaded sum (no per-row SUM fallback query)
        $jobCardItems = collect($response->viewData('jobCards')->items());
        foreach ($jobCardItems as $jobCard) {
            $this->assertArrayHasKey('payments_sum_amount', $jobCard->getAttributes());
        }
        $this->assertEquals(100.0, (float) $jobCardItems->firstWhere('id', $paidJob->id)->payments_sum_amount);
        $this->assertEquals('partially_paid', $jobCardItems->firstWhere('id', $paidJob->id)->payment_status_label);
        $this->assertEquals('unpaid', $jobCardItems->firstWhere('id', $unpaidJob->id)->payment_status_label);

        $invoiceResponse = $this->actingAs($user)->get(route('counter-invoices.index'));
        $invoiceResponse->assertOk()->assertSee('Partially Paid');
        foreach ($invoiceResponse->viewData('invoices')->items() as $inv) {
            $this->assertArrayHasKey('payments_sum_amount', $inv->getAttributes());
        }
    }

    public function test_single_record_without_eager_load_still_computes_live_amounts()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = Vehicle::create(['customer_id' => $customer->id, 'registration_no' => 'GJ-01-AB-1234', 'vehicle_brand' => 'Ola', 'model' => 'S1 Pro']);

        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
            'grand_total' => 500,
        ]);

        // Fresh model (no withSum): accessor falls back to a live SUM query
        $this->assertSame(0.0, (float) $jobCard->fresh()->amount_paid);
        $this->assertSame(500.0, (float) $jobCard->fresh()->outstanding);

        $this->makePayment($jobCard->id, JobCard::class);

        $this->actingAs($user)
            ->get(route('job-cards.show', $jobCard))
            ->assertOk()
            ->assertSee('Partially Paid')
            ->assertSee('₹100.00')
            ->assertSee('₹400.00');
    }
}
