<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\JobCard;
use App\Models\LabourMaster;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class JobCardCreateEditTest extends TestCase
{
    use RefreshDatabase;
    use SeedsVehicleCatalog;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeTechnician(): Employee
    {
        return Employee::create([
            'name' => 'Ramesh',
            'role' => 'technician',
            'mobile' => '9876543210',
            'specialization' => 'battery',
            'active' => true,
        ]);
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

    private function storePayload(int $customerId, int $vehicleId): array
    {
        return [
            'job_date' => '2026-08-03',
            'customer_id' => $customerId,
            'vehicle_id' => $vehicleId,
            'odometer_reading_km' => 12000,
            'job_type' => 'paid_service',
            'complaints' => [
                ['description' => 'Battery not charging'],
            ],
        ];
    }

    public function test_create_then_assign_builds_lines_totals_and_stock()
    {
        $user = $this->makeUser();
        $technician = $this->makeTechnician();

        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 20);
        $partB = $this->makePart('BAT-01', 'Battery Cell', 100, 20, 20);
        $labour = LabourMaster::create(['service_name' => 'General Service', 'category' => 'general', 'standard_rate' => 200, 'taxable' => true]);

        // Step 1: basic info only — no technician, parts, labour, or totals
        $response = $this->actingAs($user)
            ->post(route('job-cards.store'), $this->storePayload($customer->id, $vehicle->id))
            ->assertSessionHasNoErrors();

        $jobCard = JobCard::first();

        $this->assertNotNull($jobCard);
        $response->assertRedirect(route('job-cards.assign', $jobCard));
        $this->assertStringStartsWith('JC-', $jobCard->job_card_no);
        $this->assertEquals('received', $jobCard->status);
        $this->assertEquals($customer->id, $jobCard->customer_id);
        $this->assertEquals($vehicle->id, $jobCard->vehicle_id);
        $this->assertNull($jobCard->assigned_technician_id);
        $this->assertCount(1, $jobCard->complaints);
        $this->assertCount(0, $jobCard->parts);
        $this->assertCount(0, $jobCard->labours);
        $this->assertEquals(0, (float) $jobCard->grand_total);

        // Step 2: assign & bill
        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), [
                'assigned_technician_id' => $technician->id,
                'parts' => [
                    // Client-submitted mrps (999) must be ignored in favour of the master prices
                    ['spare_part_id' => $partA->id, 'quantity' => 2, 'mrp' => 999, 'labor_charge' => 10],
                    ['spare_part_id' => $partB->id, 'quantity' => 1, 'mrp' => 999, 'labor_charge' => 20, 'under_warranty' => '1'],
                ],
                'labours' => [
                    ['labour_master_id' => $labour->id, 'technician_id' => $technician->id, 'rate' => 200, 'qty' => 1],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $jobCard->refresh();

        $this->assertEquals($technician->id, $jobCard->assigned_technician_id);
        $this->assertCount(2, $jobCard->parts);
        $this->assertCount(1, $jobCard->labours);

        // MRP always comes from the part master, never from the client submission
        $this->assertEquals(50, (float) $jobCard->parts->firstWhere('spare_part_id', $partA->id)->mrp);
        $this->assertEquals(100, (float) $jobCard->parts->firstWhere('spare_part_id', $partB->id)->mrp);

        // Warranty part is not charged but still line item
        $this->assertEquals(0, (float) $jobCard->parts->firstWhere('spare_part_id', $partB->id)->line_total);
        $this->assertEquals(1, (int) $jobCard->parts->firstWhere('spare_part_id', $partB->id)->under_warranty);

        // Totals: partA 2*(50+10)=120 + partB 0 (warranty) + labour 200 = 320
        $this->assertEquals(120, (float) $jobCard->parts->whereNotIn('spare_part_id', [$partB->id])->sum('line_total'));
        $this->assertEquals(200, (float) $jobCard->labours->sum('line_total'));
        $this->assertEqualsWithDelta(320, (float) $jobCard->grand_total, 0.01);
        $this->assertEquals(0, (float) $jobCard->cgst_amount);
        $this->assertEquals(0, (float) $jobCard->sgst_amount);

        // Stock deducted for BOTH parts (warranty part physically used)
        $this->assertEquals(18, (int) $partA->fresh()->current_stock_qty);
        $this->assertEquals(19, (int) $partB->fresh()->current_stock_qty);
    }

    public function test_assign_resyncs_line_items_recalculates_totals_and_adjusts_stock()
    {
        $user = $this->makeUser();
        $technician = $this->makeTechnician();

        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 20);
        $partB = $this->makePart('BAT-01', 'Battery Cell', 100, 20, 20);
        $partC = $this->makePart('MOT-01', 'Motor Spares', 30, 5, 20);
        $labour = LabourMaster::create(['service_name' => 'General Service', 'category' => 'general', 'standard_rate' => 200, 'taxable' => true]);

        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
        ]);

        // Build the same initial set as the create test
        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), [
                'assigned_technician_id' => $technician->id,
                'parts' => [
                    // Client-submitted mrps (999) must be ignored in favour of the master prices
                    ['spare_part_id' => $partA->id, 'quantity' => 2, 'mrp' => 999, 'labor_charge' => 10],
                    ['spare_part_id' => $partB->id, 'quantity' => 1, 'mrp' => 999, 'labor_charge' => 20, 'under_warranty' => '1'],
                ],
                'labours' => [
                    ['labour_master_id' => $labour->id, 'technician_id' => $technician->id, 'rate' => 200, 'qty' => 1],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(18, (int) $partA->fresh()->current_stock_qty);
        $this->assertEquals(19, (int) $partB->fresh()->current_stock_qty);

        // Re-assign: change part A qty 2->5, remove the labour line, add part C, enable GST
        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), [
                'assigned_technician_id' => $technician->id,
                'gst_applicable' => '1',
                'parts' => [
                    ['spare_part_id' => $partA->id, 'quantity' => 5, 'mrp' => 999, 'labor_charge' => 10],
                    ['spare_part_id' => $partB->id, 'quantity' => 1, 'mrp' => 999, 'labor_charge' => 20, 'under_warranty' => '1'],
                    ['spare_part_id' => $partC->id, 'quantity' => 3, 'mrp' => 999, 'labor_charge' => 5],
                ],
                'labours' => [],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $jobCard->refresh();

        // Line items fully replaced: no duplicates, no leftovers
        $this->assertCount(3, $jobCard->parts);
        $this->assertCount(0, $jobCard->labours);
        $this->assertEquals(50, (float) $jobCard->parts->firstWhere('spare_part_id', $partA->id)->mrp);
        $this->assertEquals(30, (float) $jobCard->parts->firstWhere('spare_part_id', $partC->id)->mrp);
        $this->assertEquals(300, (float) $jobCard->parts->firstWhere('spare_part_id', $partA->id)->line_total);
        $this->assertEquals(0, (float) $jobCard->parts->firstWhere('spare_part_id', $partB->id)->line_total);
        $this->assertEquals(105, (float) $jobCard->parts->firstWhere('spare_part_id', $partC->id)->line_total);

        // Totals: partA 5*60=300 + partB 0 + partC 3*35=105 = 405
        // GST 18% on partA (300) + partC (105) only; partB is warranty
        // Pre-rounding total 477.90 -> rounds to 478.00, round-off +0.10
        $this->assertEqualsWithDelta(405, (float) ($jobCard->grand_total - $jobCard->cgst_amount - $jobCard->sgst_amount - $jobCard->round_off_amount), 0.01);
        $this->assertEqualsWithDelta(36.45, (float) $jobCard->cgst_amount, 0.01);
        $this->assertEqualsWithDelta(36.45, (float) $jobCard->sgst_amount, 0.01);
        $this->assertEqualsWithDelta(0.10, (float) $jobCard->round_off_amount, 0.01);
        $this->assertEqualsWithDelta(478.00, (float) $jobCard->grand_total, 0.01);

        // Stock: A -> 15 (5 total), B -> 19 (1 total), C -> 17 (3 total)
        $this->assertEquals(15, (int) $partA->fresh()->current_stock_qty);
        $this->assertEquals(19, (int) $partB->fresh()->current_stock_qty);
        $this->assertEquals(17, (int) $partC->fresh()->current_stock_qty);

        // Basic edit: update status + complaints only, line items stay untouched
        $this->actingAs($user)
            ->put(route('job-cards.update', $jobCard), [
                'job_date' => '2026-08-04',
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'odometer_reading_km' => 12000,
                'job_type' => 'paid_service',
                'status' => 'in_progress',
                'complaints' => [
                    ['description' => 'Battery not charging'],
                    ['description' => 'Check brakes too'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $jobCard->refresh();

        $this->assertEquals('in_progress', $jobCard->status);
        $this->assertEquals('2026-08-04', $jobCard->job_date->format('Y-m-d'));
        $this->assertCount(2, $jobCard->complaints);
        $this->assertCount(3, $jobCard->parts);
        $this->assertCount(0, $jobCard->labours);
        $this->assertEquals(478.00, (float) $jobCard->grand_total);
    }

    public function test_store_rejects_vehicle_that_does_not_belong_to_customer()
    {
        $user = $this->makeUser();

        $customerA = Customer::create(['first_name' => 'A', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $customerB = Customer::create(['first_name' => 'B', 'mobile' => '9000000002', 'customer_type' => 'regular']);
        $vehicleOfB = $this->makeCatalogVehicle($customerB, 'GJ-01-CD-5678', 'Ather', '450X');

        $this->actingAs($user)
            ->post(route('job-cards.store'), [
                'job_date' => '2026-08-03',
                'customer_id' => $customerA->id,
                'vehicle_id' => $vehicleOfB->id,
                'job_type' => 'paid_service',
                'complaints' => [
                    ['description' => 'Brake noise'],
                ],
            ])
            ->assertSessionHasErrors('vehicle_id');

        $this->assertDatabaseCount('job_cards', 0);
    }

    public function test_create_edit_and_assign_pages_render()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $response = $this->get(route('job-cards.create'));
        $response->assertOk();
        $response->assertSee('Create New Job Card');
        $response->assertSee('jobCardForm');

        $customer = Customer::create(['first_name' => 'A', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-CD-5678', 'Ather', '450X');
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 20);
        $technician = $this->makeTechnician();

        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
        ]);
        $jobCard->complaints()->create(['description' => 'Brake noise']);
        $jobCard->parts()->create([
            'spare_part_id' => $partA->id,
            'quantity' => 1,
            'mrp' => 50,
            'labor_charge' => 10,
            'under_warranty' => false,
            'line_total' => 60,
        ]);

        $response = $this->get(route('job-cards.edit', $jobCard));
        $response->assertOk();
        $response->assertSee('Edit Job Card ' . $jobCard->job_card_no);
        $response->assertSee('jobCardForm');
        $response->assertSee('Brake noise');

        $response = $this->get(route('job-cards.assign', $jobCard));
        $response->assertOk();
        $response->assertSee('Assign & Bill', false);
        $response->assertSee('Brake Shoe');
        $response->assertSee('Ramesh');
    }
}
