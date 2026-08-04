<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\JobCard;
use App\Models\LabourMaster;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MixedGstRateTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makePart(string $code, string $name, float $mrp, int $stock, ?float $gstRate): SparePart
    {
        return SparePart::create([
            'part_name' => $name,
            'part_code' => $code,
            'category' => 'battery',
            'unit' => 'pcs',
            'purchase_price' => $mrp * 0.6,
            'mrp' => $mrp,
            'labor_charge' => 0,
            'gst_rate' => $gstRate,
            'current_stock_qty' => $stock,
            'reorder_level' => 5,
        ]);
    }

    private function makeLabour(string $service, float $rate, bool $taxable, ?float $gstRate): LabourMaster
    {
        return LabourMaster::create([
            'service_name' => $service,
            'category' => 'general',
            'standard_rate' => $rate,
            'taxable' => $taxable,
            'gst_rate' => $gstRate,
        ]);
    }

    private function jobCardPayload(int $customerId, int $vehicleId, int $partAId, int $partBId, int $labour1Id, int $labour2Id, bool $gst): array
    {
        $payload = [
            'job_date' => '2026-08-03',
            'customer_id' => $customerId,
            'vehicle_id' => $vehicleId,
            'job_type' => 'paid_service',
            'status' => 'received',
            'complaints' => [
                ['description' => 'Noise', 'charge' => '100'],
            ],
            'parts' => [
                ['spare_part_id' => $partAId, 'quantity' => 2, 'mrp' => 100, 'labor_charge' => 0],
                ['spare_part_id' => $partBId, 'quantity' => 1, 'mrp' => 50, 'labor_charge' => 0],
            ],
            'labours' => [
                ['labour_master_id' => $labour1Id, 'rate' => 100, 'qty' => 1],
                ['labour_master_id' => $labour2Id, 'rate' => 50, 'qty' => 1],
            ],
        ];

        if ($gst) {
            $payload['gst_applicable'] = '1';
        }

        return $payload;
    }

    public function test_job_card_taxes_only_lines_with_a_gst_rate()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = Vehicle::create(['customer_id' => $customer->id, 'registration_no' => 'GJ-01-AB-1234', 'vehicle_brand' => 'Ola', 'model' => 'S1 Pro']);
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 100, 20, 18);
        $partB = $this->makePart('OIL-01', 'Loose Oil', 50, 20, null);
        $labour1 = $this->makeLabour('General Service', 100, true, 18);
        $labour2 = $this->makeLabour('Free Checkup', 50, false, 18);

        $this->actingAs($user)
            ->post(route('job-cards.store'), $this->jobCardPayload($customer->id, $vehicle->id, $partA->id, $partB->id, $labour1->id, $labour2->id, true))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $jobCard = JobCard::first();
        $this->assertNotNull($jobCard);

        // Subtotal: complaint 100 + partA 200 + partB 50 + labour1 100 + labour2 50 = 500
        // Tax only on partA (200 @18% = 36) and labour1 (100 @18% = 18) -> 54 total
        $this->assertEqualsWithDelta(500, (float) ($jobCard->grand_total - $jobCard->cgst_amount - $jobCard->sgst_amount), 0.01);
        $this->assertEqualsWithDelta(27.00, (float) $jobCard->cgst_amount, 0.01);
        $this->assertEqualsWithDelta(27.00, (float) $jobCard->sgst_amount, 0.01);
        $this->assertEqualsWithDelta(0.00, (float) $jobCard->round_off_amount, 0.01);
        $this->assertEqualsWithDelta(554.00, (float) $jobCard->grand_total, 0.01);
    }

    public function test_job_card_with_gst_disabled_has_no_tax_even_with_rates()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = Vehicle::create(['customer_id' => $customer->id, 'registration_no' => 'GJ-01-AB-1234', 'vehicle_brand' => 'Ola', 'model' => 'S1 Pro']);
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 100, 20, 18);
        $partB = $this->makePart('OIL-01', 'Loose Oil', 50, 20, null);
        $labour1 = $this->makeLabour('General Service', 100, true, 18);
        $labour2 = $this->makeLabour('Free Checkup', 50, false, 18);

        $this->actingAs($user)
            ->post(route('job-cards.store'), $this->jobCardPayload($customer->id, $vehicle->id, $partA->id, $partB->id, $labour1->id, $labour2->id, false))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $jobCard = JobCard::first();
        $this->assertEquals(0, (float) $jobCard->cgst_amount);
        $this->assertEquals(0, (float) $jobCard->sgst_amount);
        $this->assertEqualsWithDelta(500, (float) $jobCard->grand_total, 0.01);
    }

    public function test_estimate_store_taxes_only_lines_with_a_gst_rate()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = Vehicle::create(['customer_id' => $customer->id, 'registration_no' => 'GJ-01-AB-1234', 'vehicle_brand' => 'Ola', 'model' => 'S1 Pro']);
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 100, 20, 18);
        $partB = $this->makePart('OIL-01', 'Loose Oil', 50, 20, null);
        $labour1 = $this->makeLabour('General Service', 100, true, 18);
        $labour2 = $this->makeLabour('Free Checkup', 50, false, 18);

        $this->actingAs($user)
            ->post(route('estimates.store'), [
                'estimate_date' => '2026-08-03',
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'status' => 'draft',
                'gst_applicable' => '1',
                'parts' => [
                    ['spare_part_id' => $partA->id, 'quantity' => 2, 'mrp' => 100, 'labor_charge' => 0],
                    ['spare_part_id' => $partB->id, 'quantity' => 1, 'mrp' => 50, 'labor_charge' => 0],
                ],
                'labours' => [
                    ['labour_master_id' => $labour1->id, 'rate' => 100, 'qty' => 1],
                    ['labour_master_id' => $labour2->id, 'rate' => 50, 'qty' => 1],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $estimate = \App\Models\Estimate::first();
        $this->assertNotNull($estimate);
        $this->assertEqualsWithDelta(400, (float) ($estimate->grand_total - $estimate->cgst_amount - $estimate->sgst_amount), 0.01);
        $this->assertEqualsWithDelta(27.00, (float) $estimate->cgst_amount, 0.01);
        $this->assertEqualsWithDelta(27.00, (float) $estimate->sgst_amount, 0.01);
        $this->assertEqualsWithDelta(0.00, (float) $estimate->round_off_amount, 0.01);
        $this->assertEqualsWithDelta(454.00, (float) $estimate->grand_total, 0.01);
    }
}
