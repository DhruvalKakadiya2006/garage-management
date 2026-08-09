<?php

namespace Tests\Feature;

use App\Models\CounterInvoice;
use App\Models\Customer;
use App\Models\JobCard;
use App\Models\LabourMaster;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class StockIntegrityTest extends TestCase
{
    use RefreshDatabase;
    use SeedsVehicleCatalog;

    private function makeUser(): User
    {
        return User::factory()->create();
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

    private function makeJobCard(Customer $customer, Vehicle $vehicle): JobCard
    {
        return JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
        ]);
    }

    private function storePayload(Customer $customer, Vehicle $vehicle): array
    {
        return [
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
        ];
    }

    private function assignPayload(array $parts): array
    {
        return ['parts' => $parts];
    }

    public function test_deleting_job_card_returns_part_stock()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $part = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 10);

        $this->actingAs($user)
            ->post(route('job-cards.store'), $this->storePayload($customer, $vehicle))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $jobCard = JobCard::first();
        $this->assertNotNull($jobCard);

        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), $this->assignPayload([
                ['spare_part_id' => $part->id, 'quantity' => 2, 'mrp' => 50, 'labor_charge' => 10],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(8, (int) $part->fresh()->current_stock_qty);

        $this->actingAs($user)
            ->delete(route('job-cards.destroy', $jobCard))
            ->assertRedirect(route('job-cards.index'));

        $this->assertDatabaseMissing('job_cards', ['id' => $jobCard->id]);
        $this->assertDatabaseMissing('job_card_parts', ['job_card_id' => $jobCard->id]);
        $this->assertEquals(10, (int) $part->fresh()->current_stock_qty);
    }

    public function test_deleting_counter_invoice_returns_item_stock()
    {
        $user = $this->makeUser();
        $part = $this->makePart('BRK-01', 'Brake Shoe', 50, 0, 10);

        $this->actingAs($user)
            ->post(route('counter-invoices.store'), [
                'invoice_date' => '2026-08-03',
                'items' => [
                    ['spare_part_id' => $part->id, 'quantity' => 3, 'mrp' => 50, 'discount_percent' => 0],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(7, (int) $part->fresh()->current_stock_qty);

        $invoice = CounterInvoice::first();

        $this->actingAs($user)
            ->delete(route('counter-invoices.destroy', $invoice))
            ->assertRedirect(route('counter-invoices.index'));

        $this->assertDatabaseMissing('counter_invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('counter_invoice_items', ['counter_invoice_id' => $invoice->id]);
        $this->assertEquals(10, (int) $part->fresh()->current_stock_qty);
    }

    public function test_job_card_assign_rejects_quantity_exceeding_stock()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $part = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 2);

        $this->actingAs($user)
            ->post(route('job-cards.store'), $this->storePayload($customer, $vehicle))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $jobCard = JobCard::first();

        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), $this->assignPayload([
                ['spare_part_id' => $part->id, 'quantity' => 5, 'mrp' => 50, 'labor_charge' => 10],
            ]))
            ->assertSessionHasErrors(['parts.0.quantity']);

        $this->assertStringContainsString('Brake Shoe', session('errors')->first('parts.0.quantity'));
        $this->assertStringContainsString('2', session('errors')->first('parts.0.quantity'));

        $this->assertDatabaseCount('job_cards', 1);
        $this->assertDatabaseCount('job_card_parts', 0);
        $this->assertEquals(2, (int) $part->fresh()->current_stock_qty);
    }

    public function test_counter_invoice_store_rejects_quantity_exceeding_stock()
    {
        $user = $this->makeUser();
        $part = $this->makePart('BRK-01', 'Brake Shoe', 50, 0, 2);

        $this->actingAs($user)
            ->post(route('counter-invoices.store'), [
                'invoice_date' => '2026-08-03',
                'items' => [
                    ['spare_part_id' => $part->id, 'quantity' => 5, 'mrp' => 50, 'discount_percent' => 0],
                ],
            ])
            ->assertSessionHasErrors(['items.0.quantity']);

        $this->assertStringContainsString('Brake Shoe', session('errors')->first('items.0.quantity'));
        $this->assertStringContainsString('2', session('errors')->first('items.0.quantity'));

        $this->assertDatabaseCount('counter_invoices', 0);
        $this->assertDatabaseCount('counter_invoice_items', 0);
        $this->assertEquals(2, (int) $part->fresh()->current_stock_qty);
    }

    public function test_job_card_assign_swapping_part_adjusts_stock_on_both_parts()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 10);
        $partB = $this->makePart('BAT-01', 'Battery Cell', 100, 20, 10);

        $jobCard = $this->makeJobCard($customer, $vehicle);

        // Start with part A, qty 2
        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), $this->assignPayload([
                ['spare_part_id' => $partA->id, 'quantity' => 2, 'mrp' => 50, 'labor_charge' => 10],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(8, (int) $partA->fresh()->current_stock_qty);
        $this->assertEquals(10, (int) $partB->fresh()->current_stock_qty);

        // Swap to part B, qty 3
        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), $this->assignPayload([
                ['spare_part_id' => $partB->id, 'quantity' => 3, 'mrp' => 100, 'labor_charge' => 20],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(10, (int) $partA->fresh()->current_stock_qty);
        $this->assertEquals(7, (int) $partB->fresh()->current_stock_qty);

        $jobCard->refresh();
        $this->assertCount(1, $jobCard->parts);
        $this->assertEquals($partB->id, $jobCard->parts->first()->spare_part_id);
    }

    public function test_job_card_assign_rejects_over_stock_accounting_for_current_line()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 10);

        $jobCard = $this->makeJobCard($customer, $vehicle);

        // Start with part A, qty 6 (stock 10 -> 4)
        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), $this->assignPayload([
                ['spare_part_id' => $partA->id, 'quantity' => 6, 'mrp' => 50, 'labor_charge' => 10],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(4, (int) $partA->fresh()->current_stock_qty);

        // Bump to qty 12: available = 4 in stock + 6 already on this line = 10 -> rejected
        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), $this->assignPayload([
                ['spare_part_id' => $partA->id, 'quantity' => 12, 'mrp' => 50, 'labor_charge' => 10],
            ]))
            ->assertSessionHasErrors(['parts.0.quantity']);

        $this->assertEquals(4, (int) $partA->fresh()->current_stock_qty);
        $jobCard->refresh();
        $this->assertEquals(6, (int) $jobCard->parts->first()->quantity);
    }

    public function test_job_card_part_model_swap_moves_stock_between_parts()
    {
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 5);
        $partB = $this->makePart('BAT-01', 'Battery Cell', 100, 20, 5);

        $jobCard = $this->makeJobCard($customer, $vehicle);
        $line = $jobCard->parts()->create([
            'spare_part_id' => $partA->id,
            'quantity' => 2,
            'mrp' => 50,
            'labor_charge' => 10,
            'under_warranty' => false,
            'line_total' => 120,
        ]);

        $this->assertEquals(3, (int) $partA->fresh()->current_stock_qty);

        // Swap spare part from A to B and change quantity
        $line->update([
            'spare_part_id' => $partB->id,
            'quantity' => 3,
            'mrp' => 100,
            'labor_charge' => 20,
            'line_total' => 360,
        ]);

        $this->assertEquals(5, (int) $partA->fresh()->current_stock_qty);
        $this->assertEquals(2, (int) $partB->fresh()->current_stock_qty);

        // Quantity-only bump on the same part (3 -> 4): B stock 2 -> 1
        $line->update(['quantity' => 4, 'line_total' => 480]);
        $this->assertEquals(5, (int) $partA->fresh()->current_stock_qty);
        $this->assertEquals(1, (int) $partB->fresh()->current_stock_qty);
    }

    public function test_counter_invoice_item_model_swap_moves_stock_between_parts()
    {
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 0, 5);
        $partB = $this->makePart('BAT-01', 'Battery Cell', 100, 0, 5);

        $invoice = CounterInvoice::create([
            'invoice_date' => '2026-08-03',
            'gst_applicable' => false,
        ]);
        $item = $invoice->items()->create([
            'spare_part_id' => $partA->id,
            'quantity' => 2,
            'mrp' => 50,
            'discount_percent' => 0,
            'line_total' => 100,
        ]);

        $this->assertEquals(3, (int) $partA->fresh()->current_stock_qty);

        // Swap spare part from A to B and change quantity
        $item->update([
            'spare_part_id' => $partB->id,
            'quantity' => 3,
            'mrp' => 100,
            'discount_percent' => 0,
            'line_total' => 300,
        ]);

        $this->assertEquals(5, (int) $partA->fresh()->current_stock_qty);
        $this->assertEquals(2, (int) $partB->fresh()->current_stock_qty);
    }

    public function test_creating_guard_prevents_overselling_at_model_level()
    {
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $part = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 3);

        $jobCard = $this->makeJobCard($customer, $vehicle);
        try {
            $jobCard->parts()->create([
                'spare_part_id' => $part->id,
                'quantity' => 5,
                'mrp' => 50,
                'labor_charge' => 10,
                'under_warranty' => false,
                'line_total' => 300,
            ]);
            $this->fail('Expected RuntimeException for overselling a job card part.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
            $this->assertStringContainsString('Brake Shoe', $e->getMessage());
        }

        $this->assertEquals(3, (int) $part->fresh()->current_stock_qty);
        $this->assertDatabaseCount('job_card_parts', 0);

        $invoice = CounterInvoice::create([
            'invoice_date' => '2026-08-03',
            'gst_applicable' => false,
        ]);
        try {
            $invoice->items()->create([
                'spare_part_id' => $part->id,
                'quantity' => 5,
                'mrp' => 50,
                'discount_percent' => 0,
                'line_total' => 250,
            ]);
            $this->fail('Expected RuntimeException for overselling a counter invoice item.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
            $this->assertStringContainsString('Brake Shoe', $e->getMessage());
        }

        $this->assertEquals(3, (int) $part->fresh()->current_stock_qty);
        $this->assertDatabaseCount('counter_invoice_items', 0);
    }

    public function test_database_cascade_no_longer_silently_deletes_parts()
    {
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $part = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 10);

        $jobCard = $this->makeJobCard($customer, $vehicle);
        $jobCard->parts()->create([
            'spare_part_id' => $part->id,
            'quantity' => 2,
            'mrp' => 50,
            'labor_charge' => 10,
            'under_warranty' => false,
            'line_total' => 120,
        ]);

        $this->assertEquals(8, (int) $part->fresh()->current_stock_qty);

        // Bypassing the controller (no manual child deletes) must now fail loudly
        // thanks to the restrict FK, instead of silently losing stock.
        try {
            $jobCard->delete();
            $this->fail('Expected QueryException when deleting a job card that still has parts.');
        } catch (QueryException $e) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('job_cards', ['id' => $jobCard->id]);
        $this->assertDatabaseHas('job_card_parts', ['job_card_id' => $jobCard->id]);
        $this->assertEquals(8, (int) $part->fresh()->current_stock_qty);
    }
}
