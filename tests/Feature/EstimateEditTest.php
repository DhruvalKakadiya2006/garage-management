<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Estimate;
use App\Models\LabourMaster;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstimateEditTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makePart(string $code, string $name, float $mrp, float $labor): SparePart
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
            'current_stock_qty' => 50,
            'reorder_level' => 5,
        ]);
    }

    private function makeLabour(string $name, float $rate): LabourMaster
    {
        return LabourMaster::create([
            'service_name' => $name,
            'category' => 'general',
            'standard_rate' => $rate,
            'taxable' => true,
        ]);
    }

    private function makeCustomerWithVehicle(): array
    {
        $customer = Customer::create(['name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = Vehicle::create(['customer_id' => $customer->id, 'registration_no' => 'GJ-01-AB-1234', 'vehicle_brand' => 'Ola', 'model' => 'S1 Pro']);
        return [$customer, $vehicle];
    }

    private function headerPayload(int $customerId, int $vehicleId): array
    {
        return [
            'estimate_date' => '2026-08-03',
            'customer_id' => $customerId,
            'vehicle_id' => $vehicleId,
            'status' => 'draft',
        ];
    }

    public function test_edit_page_prefills_existing_parts_and_labours()
    {
        $user = $this->makeUser();
        [$customer, $vehicle] = $this->makeCustomerWithVehicle();
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 10);
        $partB = $this->makePart('BAT-01', 'Battery Cell', 100, 20);
        $labour = $this->makeLabour('General Service', 200);

        $this->actingAs($user)->post(route('estimates.store'), array_merge(
            $this->headerPayload($customer->id, $vehicle->id),
            [
                'parts' => [
                    ['spare_part_id' => $partA->id, 'quantity' => 2, 'mrp' => 50, 'labor_charge' => 10],
                    ['spare_part_id' => $partB->id, 'quantity' => 1, 'mrp' => 100, 'labor_charge' => 20],
                ],
                'labours' => [
                    ['labour_master_id' => $labour->id, 'rate' => 200, 'qty' => 1],
                ],
            ]
        ))->assertRedirect();

        $estimate = Estimate::first();
        $this->assertNotNull($estimate);

        $response = $this->actingAs($user)->get(route('estimates.edit', $estimate));
        $response->assertOk();

        // The Alpine initial data must contain all three pre-filled lines
        $response->assertSee('"spare_part_id":' . $partA->id, false);
        $response->assertSee('"spare_part_id":' . $partB->id, false);
        $response->assertSee('"lineTotal":120', false);
        $response->assertSee('"labour_master_id":' . $labour->id, false);
        $response->assertSee('"lineTotal":200', false);
    }

    public function test_update_syncs_parts_and_labours_without_wiping_or_duplicating()
    {
        $user = $this->makeUser();
        [$customer, $vehicle] = $this->makeCustomerWithVehicle();
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 10);
        $partB = $this->makePart('BAT-01', 'Battery Cell', 100, 20);
        $labour1 = $this->makeLabour('General Service', 200);
        $labour2 = $this->makeLabour('Washing', 150);

        $estimate = Estimate::create([
            'estimate_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'draft',
            'gst_applicable' => false,
        ]);
        $estimate->parts()->create(['spare_part_id' => $partA->id, 'quantity' => 2, 'mrp' => 50, 'labor_charge' => 10, 'line_total' => 120]);
        $estimate->parts()->create(['spare_part_id' => $partB->id, 'quantity' => 1, 'mrp' => 100, 'labor_charge' => 20, 'line_total' => 120]);
        $estimate->labours()->create(['labour_master_id' => $labour1->id, 'rate' => 200, 'qty' => 1, 'line_total' => 200]);

        $this->actingAs($user)->get(route('estimates.edit', $estimate))->assertOk();

        // Edit: remove part B, add a new labour line (Washing)
        $this->actingAs($user)->put(route('estimates.update', $estimate), array_merge(
            $this->headerPayload($customer->id, $vehicle->id),
            [
                'parts' => [
                    ['spare_part_id' => $partA->id, 'quantity' => 2, 'mrp' => 50, 'labor_charge' => 10],
                ],
                'labours' => [
                    ['labour_master_id' => $labour1->id, 'rate' => 200, 'qty' => 1],
                    ['labour_master_id' => $labour2->id, 'rate' => 150, 'qty' => 2],
                ],
            ]
        ))->assertRedirect()->assertSessionHasNoErrors();

        $estimate->refresh();

        // Exactly the expected lines — no wipe, no duplicates
        $this->assertCount(1, $estimate->parts);
        $this->assertSame([$partA->id], $estimate->parts->pluck('spare_part_id')->toArray());
        $this->assertCount(2, $estimate->labours);
        $this->assertSame([$labour1->id, $labour2->id], $estimate->labours->pluck('labour_master_id')->sort()->values()->toArray());

        // Line totals correct
        $this->assertEquals(120.0, (float) $estimate->parts->first()->line_total);
        $this->assertEquals(200.0, (float) $estimate->labours->firstWhere('labour_master_id', $labour1->id)->line_total);
        $this->assertEquals(300.0, (float) $estimate->labours->firstWhere('labour_master_id', $labour2->id)->line_total);

        // Grand total recalculated: 120 + 200 + 300 = 620
        $this->assertEqualsWithDelta(620, (float) $estimate->grand_total, 0.01);
    }

    public function test_update_rejects_estimate_with_no_line_items()
    {
        $user = $this->makeUser();
        [$customer, $vehicle] = $this->makeCustomerWithVehicle();
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 10);

        $estimate = Estimate::create([
            'estimate_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'draft',
            'gst_applicable' => false,
            'grand_total' => 60,
        ]);
        $estimate->parts()->create(['spare_part_id' => $partA->id, 'quantity' => 1, 'mrp' => 50, 'labor_charge' => 10, 'line_total' => 60]);

        $this->actingAs($user)->put(route('estimates.update', $estimate), array_merge(
            $this->headerPayload($customer->id, $vehicle->id),
            ['parts' => [], 'labours' => []]
        ))
            ->assertSessionHasErrors(['parts' => 'An estimate must have at least one part or labour line.']);

        // Original line items untouched
        $estimate->refresh();
        $this->assertCount(1, $estimate->parts);
        $this->assertCount(0, $estimate->labours);
        $this->assertEquals(60.0, (float) $estimate->grand_total);
    }
}
