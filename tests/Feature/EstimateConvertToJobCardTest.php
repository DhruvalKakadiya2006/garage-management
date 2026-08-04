<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Estimate;
use App\Models\JobCard;
use App\Models\LabourMaster;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GstCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstimateConvertToJobCardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeCustomer(string $name = 'Dhruval', string $mobile = '9000000001'): Customer
    {
        return Customer::create(['name' => $name, 'mobile' => $mobile, 'customer_type' => 'regular']);
    }

    private function makeVehicle(Customer $customer, string $regNo = 'GJ-01-AB-1234'): Vehicle
    {
        return Vehicle::create(['customer_id' => $customer->id, 'registration_no' => $regNo, 'vehicle_brand' => 'Ola', 'model' => 'S1 Pro']);
    }

    private function makePart(string $code, string $name, float $mrp, float $labor, int $stock): SparePart
    {
        return SparePart::create([
            'part_name' => $name,
            'part_code' => $code,
            'category' => 'battery',
            'unit' => 'pcs',
            'purchase_price' => $mrp * 0.6,
            'mrp' => $mrp,
            'labor_charge' => $labor,
            'gst_rate' => 18,
            'current_stock_qty' => $stock,
            'reorder_level' => 5,
        ]);
    }

    private function makeLabour(string $service = 'General Service', float $rate = 200): LabourMaster
    {
        return LabourMaster::create(['service_name' => $service, 'category' => 'general', 'standard_rate' => $rate, 'taxable' => true, 'gst_rate' => 18]);
    }

    private function makeApprovedEstimate(Customer $customer, Vehicle $vehicle, array $parts = [], array $labours = [], bool $gst = false, float $discountPercent = 0, string $status = 'approved'): Estimate
    {
        $lines = [];
        foreach ($parts as $p) {
            $lines[] = [
                'line_total' => $p['quantity'] * ($p['mrp'] + $p['labor_charge']),
                'gst_rate' => 18,
            ];
        }
        foreach ($labours as $l) {
            $lines[] = [
                'line_total' => $l['rate'] * $l['qty'],
                'gst_rate' => 18,
            ];
        }

        $calculations = app(GstCalculator::class)->calculate($lines, $gst, $discountPercent);

        $estimate = Estimate::create([
            'estimate_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => $status,
            'discount_percent' => $calculations['discount_percent'],
            'discount_amount' => $calculations['discount_amount'],
            'gst_applicable' => $gst,
            'cgst_amount' => $calculations['cgst_amount'],
            'sgst_amount' => $calculations['sgst_amount'],
            'round_off_amount' => $calculations['round_off_amount'],
            'grand_total' => $calculations['grand_total'],
        ]);

        foreach ($parts as $p) {
            $estimate->parts()->create([
                'spare_part_id' => $p['spare_part_id'],
                'quantity' => $p['quantity'],
                'mrp' => $p['mrp'],
                'labor_charge' => $p['labor_charge'],
                'line_total' => $p['quantity'] * ($p['mrp'] + $p['labor_charge']),
            ]);
        }
        foreach ($labours as $l) {
            $estimate->labours()->create([
                'labour_master_id' => $l['labour_master_id'],
                'rate' => $l['rate'],
                'qty' => $l['qty'],
                'line_total' => $l['rate'] * $l['qty'],
            ]);
        }

        return $estimate;
    }

    public function test_conversion_rejects_non_approved_estimate()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle($customer);
        $estimate = $this->makeApprovedEstimate($customer, $vehicle, status: 'sent');

        $this->actingAs($user)
            ->post(route('estimates.convert-to-job-card', $estimate))
            ->assertSessionHas('error', 'Only approved estimates can be converted to a job card.');

        $this->assertDatabaseCount('job_cards', 0);
        $this->assertNull($estimate->fresh()->job_card_id);
    }

    public function test_conversion_rejects_already_converted_estimate()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle($customer);
        $part = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 10);
        $estimate = $this->makeApprovedEstimate($customer, $vehicle, [
            ['spare_part_id' => $part->id, 'quantity' => 2, 'mrp' => 50, 'labor_charge' => 10],
        ]);

        $this->actingAs($user)
            ->post(route('estimates.convert-to-job-card', $estimate))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('job_cards', 1);

        $this->actingAs($user)
            ->post(route('estimates.convert-to-job-card', $estimate))
            ->assertSessionHas('error', 'This estimate has already been converted to a job card.');

        $this->assertDatabaseCount('job_cards', 1);
    }

    public function test_conversion_creates_job_card_copies_lines_and_deducts_stock()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle($customer);
        $part = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 10);
        $labour = $this->makeLabour();
        $estimate = $this->makeApprovedEstimate($customer, $vehicle, [
            ['spare_part_id' => $part->id, 'quantity' => 2, 'mrp' => 50, 'labor_charge' => 10],
        ], [
            ['labour_master_id' => $labour->id, 'rate' => 200, 'qty' => 1],
        ]);

        $response = $this->actingAs($user)
            ->post(route('estimates.convert-to-job-card', $estimate))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $jobCard = JobCard::first();
        $this->assertNotNull($jobCard);
        $response->assertRedirect(route('job-cards.show', $jobCard));

        $this->assertStringStartsWith('JC-', $jobCard->job_card_no);
        $this->assertEquals($customer->id, $jobCard->customer_id);
        $this->assertEquals($vehicle->id, $jobCard->vehicle_id);
        $this->assertEquals($estimate->id, $jobCard->source_estimate_id);
        $this->assertEquals('paid_service', $jobCard->job_type);
        $this->assertEquals('received', $jobCard->status);
        $this->assertEquals(now()->format('Y-m-d'), $jobCard->job_date->format('Y-m-d'));

        $this->assertEquals($estimate->id, $estimate->fresh()->job_card_id);

        $jobPart = $jobCard->parts->first();
        $this->assertEquals($part->id, $jobPart->spare_part_id);
        $this->assertEquals(2, $jobPart->quantity);
        $this->assertEquals(50.00, (float) $jobPart->mrp);
        $this->assertEquals(10.00, (float) $jobPart->labor_charge);
        $this->assertFalse($jobPart->under_warranty);
        $this->assertEquals(120.00, (float) $jobPart->line_total);

        $jobLabour = $jobCard->labours->first();
        $this->assertEquals($labour->id, $jobLabour->labour_master_id);
        $this->assertEquals(200.00, (float) $jobLabour->rate);
        $this->assertEquals(1.00, (float) $jobLabour->qty);

        $this->assertEquals(8, (int) $part->fresh()->current_stock_qty);
    }

    public function test_conversion_rolls_back_completely_when_any_part_is_short()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle($customer);
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 10);
        $partB = $this->makePart('BAT-01', 'Battery Cell', 100, 20, 1);
        $estimate = $this->makeApprovedEstimate($customer, $vehicle, [
            ['spare_part_id' => $partA->id, 'quantity' => 2, 'mrp' => 50, 'labor_charge' => 10],
            ['spare_part_id' => $partB->id, 'quantity' => 2, 'mrp' => 100, 'labor_charge' => 20],
        ]);

        $this->actingAs($user)
            ->post(route('estimates.convert-to-job-card', $estimate))
            ->assertSessionHas('error');

        $this->assertStringContainsString('Battery Cell', session('error'));

        $this->assertDatabaseCount('job_cards', 0);
        $this->assertDatabaseCount('job_card_parts', 0);
        $this->assertNull($estimate->fresh()->job_card_id);
        $this->assertEquals(10, (int) $partA->fresh()->current_stock_qty);
        $this->assertEquals(1, (int) $partB->fresh()->current_stock_qty);
    }

    public function test_conversion_carries_forward_gst_and_discount_with_matching_totals()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle($customer);
        $part = $this->makePart('BRK-01', 'Brake Shoe', 100, 20, 10);
        $labour = $this->makeLabour('General Service', 200);
        $estimate = $this->makeApprovedEstimate($customer, $vehicle, [
            ['spare_part_id' => $part->id, 'quantity' => 2, 'mrp' => 100, 'labor_charge' => 20],
        ], [
            ['labour_master_id' => $labour->id, 'rate' => 200, 'qty' => 1],
        ], gst: true, discountPercent: 10);

        $this->actingAs($user)
            ->post(route('estimates.convert-to-job-card', $estimate))
            ->assertSessionHasNoErrors();

        $jobCard = JobCard::first();
        $this->assertTrue($jobCard->gst_applicable);
        $this->assertEquals(10.00, (float) $jobCard->discount_percent);
        $this->assertEquals(44.00, (float) $jobCard->discount_amount);
        // Per-line taxable after 10% discount: part 2*(100+20)=240 -> 216 @18% = 38.88, labour 200 -> 180 @18% = 32.40
        // cgst/sgst: (19.44 + 16.20) each = 35.64
        $this->assertEquals(35.64, (float) $jobCard->cgst_amount);
        $this->assertEquals(35.64, (float) $jobCard->sgst_amount);
        // After discount 396 + GST 71.28 = 467.28 -> rounds to 467.00, round-off -0.28
        $this->assertEquals(-0.28, (float) $jobCard->round_off_amount);
        $this->assertEquals(467.00, (float) $jobCard->grand_total);
        $this->assertEquals((float) $estimate->grand_total, (float) $jobCard->grand_total);
        $this->assertEquals((float) $estimate->round_off_amount, (float) $jobCard->round_off_amount);
    }

    public function test_show_page_swaps_button_for_converted_badge()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle($customer);
        $part = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 10);
        $estimate = $this->makeApprovedEstimate($customer, $vehicle, [
            ['spare_part_id' => $part->id, 'quantity' => 2, 'mrp' => 50, 'labor_charge' => 10],
        ]);

        $this->actingAs($user)
            ->get(route('estimates.show', $estimate))
            ->assertOk()
            ->assertSee('Convert to Job Card')
            ->assertDontSee('Coming Soon');

        $this->actingAs($user)
            ->post(route('estimates.convert-to-job-card', $estimate))
            ->assertSessionHasNoErrors();

        $jobCard = JobCard::first();

        $this->actingAs($user)
            ->get(route('estimates.show', $estimate))
            ->assertOk()
            ->assertSee('Converted to Job Card')
            ->assertSee($jobCard->job_card_no)
            ->assertDontSee('Convert to Job Card');
    }
}
