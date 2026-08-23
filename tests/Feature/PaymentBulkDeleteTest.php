<?php

namespace Tests\Feature;

use App\Models\CounterInvoice;
use App\Models\Customer;
use App\Models\JobCard;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class PaymentBulkDeleteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsVehicleCatalog;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makePayment(int $payableId, string $payableType): Payment
    {
        return Payment::create([
            'payable_id' => $payableId,
            'payable_type' => $payableType,
            'amount' => 100,
            'payment_mode' => 'cash',
            'payment_date' => '2026-08-03',
        ]);
    }

    public function test_bulk_delete_payments_removes_all_selected(): void
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Owner', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');

        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
            'grand_total' => 500,
        ]);
        $invoice = CounterInvoice::create([
            'invoice_date' => '2026-08-03',
            'gst_applicable' => false,
            'grand_total' => 300,
        ]);

        $paymentA = $this->makePayment($jobCard->id, JobCard::class);
        $paymentB = $this->makePayment($invoice->id, CounterInvoice::class);
        $orphaned = $this->makePayment(999999, JobCard::class);

        $response = $this->actingAs($user)->postJson(route('payments.bulk-delete'), [
            'ids' => [$paymentA->id, $paymentB->id, $orphaned->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('deleted_count', 3)
            ->assertJsonPath('skipped_count', 0)
            ->assertJsonPath('message', '3 deleted');

        // Payments are soft-deleted so the audit trail survives a mistake
        $this->assertSoftDeleted('payments', ['id' => $paymentA->id]);
        $this->assertSoftDeleted('payments', ['id' => $paymentB->id]);
        $this->assertSoftDeleted('payments', ['id' => $orphaned->id]);
        $this->assertEquals(0, Payment::count());
    }

    public function test_bulk_delete_requires_ids_array(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson(route('payments.bulk-delete'), ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }
}
