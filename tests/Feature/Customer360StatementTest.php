<?php

namespace Tests\Feature;

use App\Http\Controllers\CustomerController;
use App\Models\CounterInvoice;
use App\Models\Customer;
use App\Models\JobCard;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class Customer360StatementTest extends TestCase
{
    use RefreshDatabase;
    use SeedsVehicleCatalog;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeCustomer(string $mobile = '9000000001', string $name = 'Dhruval Patel'): Customer
    {
        return Customer::create([
            'first_name' => explode(' ', $name)[0],
            'last_name' => explode(' ', $name)[1] ?? '',
            'display_name' => $name,
            'mobile' => $mobile,
            'customer_type' => 'regular',
            'address' => '12 Ring Road',
            'city' => 'Surat',
        ]);
    }

    private function makeVehicle(Customer $customer, string $reg = 'GJ-01-AB-1234'): Vehicle
    {
        return $this->makeCatalogVehicle($customer, $reg, 'Ola', 'S1 Pro', 'Black');
    }

    private function makeJobCard(Customer $customer, Vehicle $vehicle, float $grandTotal, string $date = '2026-08-03'): JobCard
    {
        return JobCard::create([
            'job_date' => $date,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'delivered',
            'grand_total' => $grandTotal,
        ]);
    }

    private function makeCounterInvoice(Customer $customer, float $grandTotal, string $date = '2026-08-10'): CounterInvoice
    {
        return CounterInvoice::create([
            'invoice_date' => $date,
            'customer_id' => $customer->id,
            'gst_applicable' => false,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'cgst_amount' => 0,
            'sgst_amount' => 0,
            'round_off_amount' => 0,
            'grand_total' => $grandTotal,
        ]);
    }

    private function makePayment($payable, float $amount, string $date = '2026-08-12'): Payment
    {
        return $payable->payments()->create([
            'amount' => $amount,
            'payment_mode' => 'upi',
            'payment_date' => $date,
            'reference_no' => 'UPI-REF',
        ]);
    }

    public function test_show_page_displays_header_summary_and_fast_actions()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle($customer);
        $jc = $this->makeJobCard($customer, $vehicle, 340);
        $this->makePayment($jc, 100);
        $ci = $this->makeCounterInvoice($customer, 500);
        $this->makePayment($ci, 200);

        $response = $this->actingAs($user)->get(route('customers.show', $customer));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('Dhruval Patel', $content);
        $this->assertStringContainsString('₹540.00', $content);   // outstanding (340+500) - (100+200)
        $this->assertStringContainsString('₹300.00', $content);   // total paid
        $this->assertStringContainsString('Create Job Card', $content);
        $this->assertStringContainsString('Add Vehicle', $content);
        $this->assertStringContainsString('Receive Payment', $content);
        $this->assertStringContainsString('Statement', $content);
        $this->assertStringContainsString('GJ-01-AB-1234', $content);
    }

    public function test_job_cards_tab_shows_per_document_balances()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer('9000000008');
        $vehicle = $this->makeVehicle($customer, 'GJ-08-NO-7890');
        $jc = $this->makeJobCard($customer, $vehicle, 340);
        $this->makePayment($jc, 100);

        $response = $this->actingAs($user)->get(route('customers.show', [
            'customer' => $customer,
            'tab' => 'job_cards',
        ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('₹240.00', $content);   // job card outstanding column
        $this->assertStringContainsString('₹100.00', $content);   // job card paid column
    }

    public function test_receive_payment_is_disabled_when_everything_is_paid()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer('9000000002');
        $vehicle = $this->makeVehicle($customer, 'GJ-02-CD-5678');
        $jc = $this->makeJobCard($customer, $vehicle, 340);
        $this->makePayment($jc, 340);

        $response = $this->actingAs($user)->get(route('customers.show', $customer));

        $content = $response->getContent();
        $this->assertStringContainsString('₹0.00', $content);     // outstanding
        $this->assertStringNotContainsString('$dispatch(\'open-modal\', \'receive-payment\')', $content);
    }

    public function test_create_job_card_preselects_single_vehicle()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer('9000000003');
        $vehicle = $this->makeVehicle($customer, 'GJ-03-EF-9012');

        $response = $this->actingAs($user)->get(route('customers.show', $customer));

        $content = $response->getContent();
        $this->assertStringContainsString('vehicle_id='.$vehicle->id, $content);
    }

    public function test_job_cards_tab_is_paginated_and_constrained()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer('9000000004');
        $vehicle = $this->makeVehicle($customer, 'GJ-04-GH-3456');
        for ($i = 0; $i < 15; $i++) {
            $this->makeJobCard($customer, $vehicle, 100, '2026-08-03');
        }

        $response = $this->actingAs($user)->get(route('customers.show', [
            'customer' => $customer,
            'tab' => 'job_cards',
        ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('JC-', $content);
        $this->assertStringContainsString('job_page=2', $content);
    }

    public function test_statement_streams_pdf_with_derived_totals()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer('9000000005');
        $vehicle = $this->makeVehicle($customer, 'GJ-05-JK-6789');
        $jc = $this->makeJobCard($customer, $vehicle, 340);
        $this->makePayment($jc, 100);
        $ci = $this->makeCounterInvoice($customer, 500);
        $this->makePayment($ci, 200);

        $response = $this->actingAs($user)->get(route('customers.statement', $customer));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
        $this->assertStringContainsString('%PDF-', $response->getContent());
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition') ?? '');
        $this->assertStringContainsString('CUSTOMER-STATEMENT-', $response->headers->get('content-disposition') ?? '');
    }

    public function test_statement_view_contains_documents_and_totals()
    {
        $customer = $this->makeCustomer('9000000006');
        $vehicle = $this->makeVehicle($customer, 'GJ-06-LM-2345');
        $jc = $this->makeJobCard($customer, $vehicle, 340);
        $this->makePayment($jc, 100);
        $ci = $this->makeCounterInvoice($customer, 500);
        $this->makePayment($ci, 200);

        $data = app(CustomerController::class)->statementData(
            $customer,
            Carbon::parse('2026-04-01')->startOfDay(),
            Carbon::parse('2026-08-17')->endOfDay(),
        );

        $html = view('pdf.customer-statement', $data)->render();

        $this->assertStringContainsString('CUSTOMER STATEMENT', $html);
        $this->assertStringContainsString('JC-', $html);
        $this->assertStringContainsString('CI-', $html);
        $this->assertStringContainsString('Rs. 340.00', $html);
        $this->assertStringContainsString('Rs. 500.00', $html);
        $this->assertStringContainsString('Rs. 840.00', $html);      // billed in range
        $this->assertStringContainsString('Rs. 300.00', $html);      // collected in range
        $this->assertStringContainsString('Rs. 540.00', $html);      // outstanding as on to
    }

    public function test_statement_rows_show_per_document_collected_and_outstanding()
    {
        $customer = $this->makeCustomer('9000000009');
        $vehicle = $this->makeVehicle($customer, 'GJ-09-PQ-0123');
        $jc = $this->makeJobCard($customer, $vehicle, 111);
        $this->makePayment($jc, 40);
        $ci = $this->makeCounterInvoice($customer, 222);
        $this->makePayment($ci, 50);

        $data = app(CustomerController::class)->statementData(
            $customer,
            Carbon::parse('2026-04-01')->startOfDay(),
            Carbon::parse('2026-08-17')->endOfDay(),
        );

        // The aliased aggregates must land on the exact attribute keys the
        // PDF view reads (paid_to / collected_in_range).
        $this->assertSame(40.0, (float) $data['jobCards'][0]->paid_to);
        $this->assertSame(40.0, (float) $data['jobCards'][0]->collected_in_range);
        $this->assertSame(50.0, (float) $data['counterInvoices'][0]->paid_to);
        $this->assertSame(50.0, (float) $data['counterInvoices'][0]->collected_in_range);

        $html = view('pdf.customer-statement', $data)->render();

        $this->assertStringContainsString('Rs. 40.00', $html);    // job card collected cell
        $this->assertStringContainsString('Rs. 71.00', $html);    // job card outstanding cell (111 - 40)
        $this->assertStringContainsString('Rs. 50.00', $html);    // invoice collected cell
        $this->assertStringContainsString('Rs. 172.00', $html);   // invoice outstanding cell (222 - 50)

        // Header totals stay distinct from the per-row figures above.
        $this->assertStringContainsString('Rs. 333.00', $html);   // billed in range
        $this->assertStringContainsString('Rs. 90.00', $html);    // collected in range total
        $this->assertStringContainsString('Rs. 243.00', $html);   // outstanding as on to
    }

    public function test_voided_payments_do_not_count_toward_customer_balances()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer('9000000010');
        $vehicle = $this->makeVehicle($customer, 'GJ-10-RS-4567');
        $jc = $this->makeJobCard($customer, $vehicle, 340);
        $this->makePayment($jc, 100);
        $voided = $this->makePayment($jc, 60);

        // Voiding a payment soft-deletes it; balances must ignore it.
        $voided->delete();

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('₹240.00');   // (340) - (100), voided 60 excluded

        $content = $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('₹240.00', $content);   // outstanding
        $this->assertStringContainsString('₹100.00', $content);   // total paid
        $this->assertStringNotContainsString('₹180.00', $content);
    }

    public function test_statement_respects_selected_date_range()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer('9000000007');
        $vehicle = $this->makeVehicle($customer, 'GJ-07-MN-4567');
        $this->makeJobCard($customer, $vehicle, 340, '2026-08-03');
        $this->makeJobCard($customer, $vehicle, 999, '2026-03-01');   // outside range

        $data = app(CustomerController::class)->statementData(
            $customer,
            Carbon::parse('2026-04-01')->startOfDay(),
            Carbon::parse('2026-08-17')->endOfDay(),
        );

        $html = view('pdf.customer-statement', $data)->render();

        $this->assertStringContainsString('Rs. 340.00', $html);
        $this->assertStringNotContainsString('Rs. 999.00', $html);

        // The route itself still streams a valid PDF.
        $response = $this->actingAs($user)->get(route('customers.statement', [
            'customer' => $customer,
            'from_date' => '2026-04-01',
            'to_date' => '2026-08-17',
        ]));
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }
}
