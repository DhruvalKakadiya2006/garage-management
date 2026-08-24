<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\JobCard;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class PaymentOverpayGuardTest extends TestCase
{
    use RefreshDatabase;
    use SeedsVehicleCatalog;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeBilledJobCard(Customer $customer, float $grandTotal): JobCard
    {
        return JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro')->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
            'grand_total' => $grandTotal,
        ]);
    }

    private function makePayment(JobCard $jobCard, float $amount): Payment
    {
        return Payment::create([
            'payable_id' => $jobCard->id,
            'payable_type' => JobCard::class,
            'amount' => $amount,
            'payment_mode' => 'cash',
            'payment_date' => '2026-08-03',
        ]);
    }

    public function test_store_rejects_payment_exceeding_outstanding_balance()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $jobCard = $this->makeBilledJobCard($customer, 500);
        $this->makePayment($jobCard, 400);

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'payable_type' => JobCard::class,
                'payable_id' => $jobCard->id,
                'amount' => '200',
                'payment_mode' => 'cash',
                'payment_date' => '2026-08-03',
            ])
            ->assertSessionHasErrors(['amount']);

        $this->assertStringContainsString('exceeds outstanding', session('errors')->first('amount'));
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_store_accepts_a_payment_matching_the_outstanding_balance_exactly()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000002', 'customer_type' => 'regular']);
        $jobCard = $this->makeBilledJobCard($customer, 500);
        $this->makePayment($jobCard, 400);

        // Exercises the full store path including the post-insert invariant.
        $this->actingAs($user)
            ->post(route('payments.store'), [
                'payable_type' => JobCard::class,
                'payable_id' => $jobCard->id,
                'amount' => '100',
                'payment_mode' => 'upi',
                'payment_date' => '2026-08-03',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(0.0, (float) $jobCard->fresh()->outstanding);
    }

    public function test_update_rejects_amount_that_would_exceed_the_grand_total()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000003', 'customer_type' => 'regular']);
        $jobCard = $this->makeBilledJobCard($customer, 500);
        $payment = $this->makePayment($jobCard, 400);

        $this->actingAs($user)
            ->put(route('payments.update', $payment), [
                'amount' => '600',
                'payment_mode' => 'cash',
                'payment_date' => '2026-08-03',
            ])
            ->assertSessionHasErrors(['amount']);

        $this->assertStringContainsString('exceeds outstanding', session('errors')->first('amount'));
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'amount' => 400]);
    }

    public function test_update_accounts_for_other_payments_when_validating()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000004', 'customer_type' => 'regular']);
        $jobCard = $this->makeBilledJobCard($customer, 500);
        $first = $this->makePayment($jobCard, 300);
        $second = $this->makePayment($jobCard, 100);

        // Raising the first payment to 350 keeps the document within its
        // grand total (350 + 100 = 450 <= 500).
        $this->actingAs($user)
            ->put(route('payments.update', $first), [
                'amount' => '350',
                'payment_mode' => 'cash',
                'payment_date' => '2026-08-03',
            ])
            ->assertRedirect(route('payments.index'));

        $this->assertDatabaseHas('payments', ['id' => $first->id, 'amount' => 350]);
        $this->assertSame(50.0, (float) $jobCard->fresh()->outstanding);

        // Raising the second past grand_total minus the other payment (350)
        // must fail: 250 + 350 = 600 > 500.
        $this->actingAs($user)
            ->put(route('payments.update', $second), [
                'amount' => '250',
                'payment_mode' => 'cash',
                'payment_date' => '2026-08-03',
            ])
            ->assertSessionHasErrors(['amount']);

        $this->assertDatabaseHas('payments', ['id' => $second->id, 'amount' => 100]);
    }

    public function test_store_reports_a_missing_payable_document_as_a_field_error()
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'payable_type' => JobCard::class,
                'payable_id' => 999999,
                'amount' => '100',
                'payment_mode' => 'cash',
                'payment_date' => '2026-08-03',
            ])
            ->assertSessionHasErrors('payable_id');

        $this->assertStringContainsString(
            'could not be found',
            session('errors')->first('payable_id')
        );
        $this->assertDatabaseCount('payments', 0);
    }
}
