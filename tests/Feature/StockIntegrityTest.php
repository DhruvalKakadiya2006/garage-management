<?php

namespace Tests\Feature;

use App\Models\CounterInvoice;
use App\Models\Customer;
use App\Models\JobCard;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

/**
 * STOCK TRACKING DISABLED
 *
 * These tests validate automatic stock increment/decrement behavior
 * that has been intentionally disabled because the Suppliers module
 * is not yet integrated.
 *
 * All tests in this file are skipped. They can be re-enabled when
 * stock tracking is restored by uncommenting the stock logic in:
 * - app/Models/JobCardPart.php
 * - app/Models/CounterInvoiceItem.php
 * - app/Http/Controllers/JobCardController.php
 * - app/Http/Controllers/CounterInvoiceController.php
 */
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

    public function test_soft_deleting_job_card_keeps_stock_and_parts_for_restore()
    {
        $this->markTestSkipped('Stock tracking disabled - Suppliers module not integrated');

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

        // Soft delete: the document is hidden but stock stays deducted and
        // part rows remain attached so a restore brings everything back.
        $this->actingAs($user)
            ->delete(route('job-cards.destroy', $jobCard))
            ->assertRedirect(route('job-cards.index'));

        $this->assertSoftDeleted('job_cards', ['id' => $jobCard->id]);
        $this->assertDatabaseHas('job_card_parts', ['job_card_id' => $jobCard->id]);
        $this->assertEquals(8, (int) $part->fresh()->current_stock_qty);
        $this->assertEquals(0, JobCard::count());

        // Restoring resurrects the document with its lines intact. Re-fetch
        // through withTrashed(): the earlier $jobCard instance predates the
        // HTTP soft delete, so its restore() would be a dirty-attribute no-op.
        JobCard::withTrashed()->findOrFail($jobCard->id)->restore();
        $this->assertEquals(1, JobCard::count());
        $this->assertCount(1, $jobCard->fresh()->parts);
        $this->assertEquals(8, (int) $part->fresh()->current_stock_qty);

        // Permanent removal returns stock via the per-part deleting hook
        foreach ($jobCard->fresh()->parts as $partRow) {
            $partRow->delete();
        }
        $jobCard->forceDelete();

        $this->assertDatabaseMissing('job_cards', ['id' => $jobCard->id]);
        $this->assertDatabaseMissing('job_card_parts', ['job_card_id' => $jobCard->id]);
        $this->assertEquals(10, (int) $part->fresh()->current_stock_qty);
    }

    public function test_deleting_counter_invoice_returns_item_stock()
    {
        $this->markTestSkipped('Stock tracking disabled - Suppliers module not integrated');

        $user = $this->makeUser();
        $part = $this->makePart('BRK-01', 'Brake Shoe', 50, 0, 10);

        $this->actingAs($user)
            ->post(route('counter-invoices.store'), [
                'invoice_date' => '2026-08-03',
                'walkin_name' => 'Walk-in Buyer',
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
        $this->markTestSkipped('Stock tracking disabled - Suppliers module not integrated');

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
        $this->markTestSkipped('Stock tracking disabled - Suppliers module not integrated');

        $user = $this->makeUser();
        $part = $this->makePart('BRK-01', 'Brake Shoe', 50, 0, 2);

        $this->actingAs($user)
            ->post(route('counter-invoices.store'), [
                'invoice_date' => '2026-08-03',
                'walkin_name' => 'Walk-in Buyer',
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
        $this->markTestSkipped('Stock tracking disabled - Suppliers module not integrated');

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
        $this->markTestSkipped('Stock tracking disabled - Suppliers module not integrated');

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

    public function test_creating_guard_prevents_overselling_at_model_level()
    {
        $this->markTestSkipped('Stock tracking disabled - Suppliers module not integrated');

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

    public function test_assign_resave_with_unchanged_lines_preserves_rows_and_stock()
    {
        $this->markTestSkipped('Stock tracking disabled - Suppliers module not integrated');

        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $part = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 10);

        $jobCard = $this->makeJobCard($customer, $vehicle);

        $payload = $this->assignPayload([
            ['spare_part_id' => $part->id, 'quantity' => 2, 'mrp' => 50, 'labor_charge' => 10],
        ]);

        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(8, (int) $part->fresh()->current_stock_qty);
        $partRowId = $jobCard->parts()->first()->id;

        // Re-submitting the exact same lines must not churn stock or rows
        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(8, (int) $part->fresh()->current_stock_qty);
        $this->assertDatabaseCount('job_card_parts', 1);
        $this->assertDatabaseHas('job_card_parts', ['id' => $partRowId]);

        // Changing one line still triggers the full rewrite path
        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), $this->assignPayload([
                ['spare_part_id' => $part->id, 'quantity' => 3, 'mrp' => 50, 'labor_charge' => 10],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(7, (int) $part->fresh()->current_stock_qty);
        $this->assertDatabaseMissing('job_card_parts', ['id' => $partRowId]);
    }

    public function test_database_cascade_no_longer_silently_deletes_parts()
    {
        $this->markTestSkipped('Stock tracking disabled - Suppliers module not integrated');

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
        // thanks to the restrict FK, instead of silently losing stock. A soft
        // delete leaves the database untouched by design — forceDelete is what
        // hits the FK guard.
        try {
            $jobCard->forceDelete();
            $this->fail('Expected QueryException when force-deleting a job card that still has parts.');
        } catch (QueryException $e) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('job_cards', ['id' => $jobCard->id]);
        $this->assertDatabaseHas('job_card_parts', ['job_card_id' => $jobCard->id]);
        $this->assertEquals(8, (int) $part->fresh()->current_stock_qty);
    }

    public function test_database_rejects_negative_stock_when_application_validation_is_bypassed()
    {
        $this->markTestSkipped('Stock tracking disabled - Suppliers module not integrated');

        $part = $this->makePart('BRK-01', 'Brake Shoe', 50, 10, 0);

        // Bypass the application layer entirely (no StockValidator, no model
        // creating hook) and force stock below zero directly on the database.
        // The CHECK constraint must reject the write.
        try {
            DB::table('spare_parts')->where('id', $part->id)->update(['current_stock_qty' => -1]);
            $this->fail('Expected QueryException when stock is forced below zero at the database level.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('CHECK', $e->getMessage());
        }

        // The rejected write left the row untouched
        $this->assertEquals(0, (int) $part->fresh()->current_stock_qty);

        // A zero-value write is still allowed
        DB::table('spare_parts')->where('id', $part->id)->update(['current_stock_qty' => 0]);
        $this->assertEquals(0, (int) $part->fresh()->current_stock_qty);
    }
}
