<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\JobCard;
use App\Models\LabourMaster;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $response->assertSee('Edit Job Card '.$jobCard->job_card_no);
        $response->assertSee('jobCardForm');
        $response->assertSee('Brake noise');

        $response = $this->get(route('job-cards.assign', $jobCard));
        $response->assertOk();
        $response->assertSee('Assign & Bill', false);
        $response->assertSee('Brake Shoe');
        $response->assertSee('Ramesh');
    }

    public function test_create_page_prefills_customer_combobox_and_vehicle_from_query_params()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $otherCustomer = Customer::create(['first_name' => 'Other', 'mobile' => '9000000002', 'customer_type' => 'regular']);
        $foreignVehicle = $this->makeCatalogVehicle($otherCustomer, 'GJ-01-AB-9999', 'Ather', '450X');

        $response = $this->actingAs($user)
            ->get(route('job-cards.create', ['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id]));
        $response->assertOk();

        // The searchableCombobox config must carry both the id and the label:
        // init() paints the label into the visible query field. Blade's @js()
        // encodes double quotes as \u0022, so match against that exact form.
        $q = '\u0022';
        $html = html_entity_decode($response->getContent(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString($q.'initialId'.$q.':'.$q.$customer->id.$q, $html);
        $this->assertStringContainsString($q.'initialLabel'.$q.':'.$q.'Dhruval', $html);
        $this->assertStringContainsString('9000000001', $html);

        // The vehicle dropdown pre-selects through the Alpine initial data.
        // Blade's @json() emits literal quotes (unlike @js()'s \u0022 above).
        $this->assertStringContainsString('"vehicle_id":"'.$vehicle->id.'"', $html);

        // A vehicle owned by somebody else is never pre-selected
        $response = $this->actingAs($user)
            ->get(route('job-cards.create', ['customer_id' => $customer->id, 'vehicle_id' => $foreignVehicle->id]));
        $response->assertOk();

        $html = html_entity_decode($response->getContent(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringNotContainsString('"vehicle_id":"'.$foreignVehicle->id.'"', $html);
        $this->assertStringContainsString('"vehicle_id":""', $html);
    }

    public function test_assign_rejects_discount_amount_exceeding_subtotal()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 20);

        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
        ]);

        // Subtotal 2 * (50 + 10) = 120; a ₹150 discount must be rejected
        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), [
                'assigned_technician_id' => $this->makeTechnician()->id,
                'discount_amount' => '150',
                'parts' => [
                    ['spare_part_id' => $partA->id, 'quantity' => 2, 'mrp' => 999, 'labor_charge' => 10],
                ],
                'labours' => [],
            ])
            ->assertSessionHasErrors(['discount_amount']);

        // Rejected before any writes: no line items, no stock movement
        $jobCard->refresh();
        $this->assertCount(0, $jobCard->parts);
        $this->assertCount(0, $jobCard->labours);
        $this->assertEquals(20, (int) $partA->fresh()->current_stock_qty);
    }

    public function test_assign_allows_discount_amount_equal_to_subtotal()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 20);

        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
        ]);

        // Discount exactly matching the subtotal is allowed and yields ₹0.00
        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), [
                'assigned_technician_id' => $this->makeTechnician()->id,
                'discount_amount' => '120',
                'parts' => [
                    ['spare_part_id' => $partA->id, 'quantity' => 2, 'mrp' => 999, 'labor_charge' => 10],
                ],
                'labours' => [],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $jobCard->refresh();
        $this->assertEquals(0, (float) $jobCard->grand_total);
    }

    public function test_assign_enforces_discount_percent_cap()
    {
        $user = $this->makeUser();
        $technician = $this->makeTechnician();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 20);

        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
        ]);

        $payload = fn (string $percent) => [
            'assigned_technician_id' => $technician->id,
            'discount_percent' => $percent,
            'parts' => [
                ['spare_part_id' => $partA->id, 'quantity' => 2, 'mrp' => 999, 'labor_charge' => 10],
            ],
            'labours' => [],
        ];

        // 100% is the ceiling: fully discounted, grand total is exactly ₹0.00
        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), $payload('100'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $jobCard->refresh();
        $this->assertEquals(0, (float) $jobCard->grand_total);

        // Anything above 100% must be rejected by validation
        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), $payload('150'))
            ->assertSessionHasErrors(['discount_percent']);
    }

    public function test_show_page_for_unbilled_job_card_does_not_claim_fully_paid()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');

        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
        ]);

        $response = $this->actingAs($user)->get(route('job-cards.show', $jobCard));
        $response->assertOk();

        // grand_total is NULL until billed: must render ₹0.00 without a
        // number_format(null) deprecation, and must NOT claim the card is paid.
        $response->assertSee('₹0.00');
        $response->assertSee('Not billed yet');
        $response->assertDontSee('Fully Paid');
    }

    public function test_index_page_labels_unbilled_job_card_correctly()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');

        JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
        ]);

        $response = $this->actingAs($user)->get(route('job-cards.index'));
        $response->assertOk();
        $response->assertSee('Unbilled');
        $response->assertSee('₹0.00');
    }

    public function test_payment_on_unbilled_job_card_is_rejected()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');

        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
        ]);

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'payable_type' => 'App\Models\JobCard',
                'payable_id' => $jobCard->id,
                'amount' => '100',
                'payment_mode' => 'cash',
                'payment_date' => '2026-08-03',
            ])
            ->assertSessionHasErrors(['amount']);

        $this->assertStringContainsString('not been billed yet', session('errors')->first('amount'));
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_creating_hook_never_persists_null_money_fields_but_keeps_grand_total_null()
    {
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');

        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            // Explicitly attempt to sneak NULL money fields through — the
            // creating hook must normalise these to 0 as a defensive default.
            'discount_amount' => null,
            'cgst_amount' => null,
            'sgst_amount' => null,
            'round_off_amount' => null,
        ]);

        $this->assertEquals(0, (float) $jobCard->fresh()->discount_amount);
        $this->assertEquals(0, (float) $jobCard->fresh()->cgst_amount);
        $this->assertEquals(0, (float) $jobCard->fresh()->sgst_amount);
        $this->assertEquals(0, (float) $jobCard->fresh()->round_off_amount);

        // grand_total is deliberately left NULL — a fresh card is unbilled.
        $this->assertNull($jobCard->fresh()->grand_total);
        $this->assertFalse($jobCard->fresh()->is_billed);
    }

    public function test_billed_job_card_cannot_be_moved_to_another_customer_or_vehicle()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $secondOwnVehicle = $this->makeCatalogVehicle($customer, 'GJ-01-CD-5678', 'Ather', '450X');
        $otherCustomer = Customer::create(['first_name' => 'Nisha', 'mobile' => '9000000002', 'customer_type' => 'regular']);
        $otherVehicle = $this->makeCatalogVehicle($otherCustomer, 'GJ-01-EF-9012', 'TVS', 'iQube');

        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'in_progress',
            'grand_total' => 500,
        ]);
        $this->assertTrue($jobCard->is_billed);

        // Moving to another customer AND their vehicle (a valid pairing) is rejected
        $this->actingAs($user)
            ->put(route('job-cards.update', $jobCard), [
                'job_date' => '2026-08-04',
                'customer_id' => $otherCustomer->id,
                'vehicle_id' => $otherVehicle->id,
                'job_type' => 'paid_service',
                'status' => 'in_progress',
            ])
            ->assertSessionHasErrors(['customer_id']);

        // Swapping to another vehicle owned by the same customer is rejected too
        $this->actingAs($user)
            ->put(route('job-cards.update', $jobCard), [
                'job_date' => '2026-08-04',
                'customer_id' => $customer->id,
                'vehicle_id' => $secondOwnVehicle->id,
                'job_type' => 'paid_service',
                'status' => 'in_progress',
            ])
            ->assertSessionHasErrors(['customer_id']);

        $jobCard->refresh();
        $this->assertEquals($customer->id, $jobCard->customer_id);
        $this->assertEquals($vehicle->id, $jobCard->vehicle_id);

        // Non-attribution edits remain allowed on a billed card
        $this->actingAs($user)
            ->put(route('job-cards.update', $jobCard), [
                'job_date' => '2026-08-05',
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'job_type' => 'paid_service',
                'status' => 'delivered',
            ])
            ->assertSessionHasNoErrors();

        $jobCard->refresh();
        $this->assertEquals('delivered', $jobCard->status);
    }

    public function test_job_card_index_validates_filter_inputs()
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('job-cards.index', ['status' => 'not-a-status']))
            ->assertRedirect()
            ->assertSessionHasErrors(['status']);

        $this->actingAs($user)
            ->get(route('job-cards.index', ['technician' => 999999]))
            ->assertRedirect()
            ->assertSessionHasErrors(['technician']);

        $this->actingAs($user)
            ->get(route('job-cards.index', ['brand' => 'NoSuchBrand']))
            ->assertRedirect()
            ->assertSessionHasErrors(['brand']);

        // A clean request still loads fine
        $this->actingAs($user)->get(route('job-cards.index'))->assertOk();
    }

    public function test_updating_job_card_syncs_complaints_by_delta()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');

        $this->actingAs($user)
            ->post(route('job-cards.store'), $this->storePayload($customer->id, $vehicle->id))
            ->assertSessionHasNoErrors();

        $jobCard = JobCard::first();
        $originalComplaintId = $jobCard->complaints()->first()->id;

        // Keep the existing complaint and add a new one
        $this->actingAs($user)
            ->put(route('job-cards.update', $jobCard), [
                'job_date' => '2026-08-03',
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'job_type' => 'paid_service',
                'status' => 'received',
                'complaints' => [
                    ['description' => 'Battery not charging'],
                    ['description' => 'Brake noise while riding'],
                ],
            ])
            ->assertSessionHasNoErrors();

        // The untouched complaint row kept its identity
        $this->assertDatabaseHas('job_card_complaints', [
            'id' => $originalComplaintId,
            'description' => 'Battery not charging',
        ]);
        $this->assertDatabaseCount('job_card_complaints', 2);

        // Removing it deletes exactly that row
        $this->actingAs($user)
            ->put(route('job-cards.update', $jobCard), [
                'job_date' => '2026-08-03',
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'job_type' => 'paid_service',
                'status' => 'received',
                'complaints' => [
                    ['description' => 'Brake noise while riding'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('job_card_complaints', ['id' => $originalComplaintId]);
        $this->assertDatabaseCount('job_card_complaints', 1);
    }

    public function test_job_card_numbering_continues_after_highest_existing_suffix()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');

        // Simulate legacy / imported data with a high number
        DB::table('job_cards')->insert([
            'job_card_no' => 'JC-0099',
            'job_date' => '2025-01-01',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('job-cards.store'), $this->storePayload($customer->id, $vehicle->id))
            ->assertSessionHasNoErrors();

        $latest = JobCard::orderByDesc('id')->first();
        $this->assertSame('JC-0100', $latest->job_card_no);
    }

    public function test_store_returns_friendly_error_when_numbering_collides()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');

        // Force the insert to violate a constraint, simulating the unique
        // index losing a numbering race between generate and insert.
        DB::statement("CREATE TRIGGER force_jc_collision BEFORE INSERT ON job_cards
            WHEN NEW.job_card_no LIKE 'JC-%'
            BEGIN SELECT RAISE(ABORT, 'simulated unique constraint'); END");

        try {
            $this->actingAs($user)
                ->post(route('job-cards.store'), $this->storePayload($customer->id, $vehicle->id))
                ->assertRedirect()
                ->assertSessionHasErrors('error');

            $this->assertStringContainsString(
                'took the next job card number',
                session('errors')->first('error')
            );
            $this->assertDatabaseCount('job_cards', 0);
        } finally {
            DB::statement('DROP TRIGGER force_jc_collision');
        }
    }

    public function test_promised_delivery_date_cannot_precede_the_job_date()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');

        $payload = $this->storePayload($customer->id, $vehicle->id);
        $payload['promised_delivery_date'] = '2026-08-01';   // job_date is 2026-08-03

        $this->actingAs($user)
            ->post(route('job-cards.store'), $payload)
            ->assertSessionHasErrors('promised_delivery_date');
    }

    public function test_store_rejects_more_than_twenty_complaints()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');

        $payload = $this->storePayload($customer->id, $vehicle->id);
        $payload['complaints'] = collect(range(1, 21))
            ->map(fn ($i) => ['description' => "Complaint {$i}"])
            ->all();

        $this->actingAs($user)
            ->post(route('job-cards.store'), $payload)
            ->assertSessionHasErrors('complaints');
    }

    public function test_assign_rejects_more_than_fifty_part_lines()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $part = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 200);

        // A billed card is required to reach the stock validation stage.
        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
        ]);

        $parts = collect(range(1, 51))
            ->map(fn () => ['spare_part_id' => $part->id, 'quantity' => 1, 'labor_charge' => 0])
            ->all();

        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), ['parts' => $parts])
            ->assertSessionHasErrors('parts');
    }
}
