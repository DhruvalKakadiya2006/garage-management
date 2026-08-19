<?php

namespace Tests\Feature;

use App\Models\CounterInvoice;
use App\Models\Customer;
use App\Models\JobCard;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class SparePartBulkDeleteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsVehicleCatalog;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makePart(string $code, string $name, int $stock = 10): SparePart
    {
        return SparePart::create([
            'part_name' => $name,
            'part_code' => $code,
            'category' => 'battery',
            'unit' => 'pcs',
            'purchase_price' => 30,
            'mrp' => 50,
            'labor_charge' => 10,
            'gst_rate' => 18,
            'current_stock_qty' => $stock,
            'reorder_level' => 5,
        ]);
    }

    public function test_bulk_delete_spare_parts_skips_parts_linked_to_documents(): void
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Owner', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');

        $linkedJob = $this->makePart('BRK-01', 'Brake Shoe');
        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
        ]);
        $jobCard->parts()->create([
            'spare_part_id' => $linkedJob->id,
            'quantity' => 1,
            'mrp' => 50,
            'labor_charge' => 10,
            'under_warranty' => false,
            'line_total' => 60,
        ]);

        $linkedInvoice = $this->makePart('BAT-01', 'Battery Cell');
        $invoice = CounterInvoice::create([
            'invoice_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'gst_applicable' => false,
        ]);
        $invoice->items()->create([
            'spare_part_id' => $linkedInvoice->id,
            'quantity' => 1,
            'mrp' => 50,
            'discount_percent' => 0,
            'line_total' => 50,
        ]);

        $freeOne = $this->makePart('OIL-01', 'Engine Oil');
        $freeTwo = $this->makePart('FIL-01', 'Oil Filter');

        $response = $this->actingAs($user)->postJson(route('spare-parts.bulk-delete'), [
            'ids' => [$linkedJob->id, $linkedInvoice->id, $freeOne->id, $freeTwo->id],
        ]);

        $response->assertOk()
            ->assertJson([
                'deleted_count' => 2,
                'skipped_count' => 2,
                'skipped_ids' => [(string) $linkedJob->id, (string) $linkedInvoice->id],
            ])
            ->assertJsonPath('message', '2 deleted, 2 skipped (linked to existing records)');

        $this->assertDatabaseMissing('spare_parts', ['id' => $freeOne->id]);
        $this->assertDatabaseMissing('spare_parts', ['id' => $freeTwo->id]);
        $this->assertDatabaseHas('spare_parts', ['id' => $linkedJob->id]);
        $this->assertDatabaseHas('spare_parts', ['id' => $linkedInvoice->id]);
    }

    public function test_bulk_delete_with_all_linked_parts_deletes_nothing(): void
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Owner', 'mobile' => '9000000002', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-5678', 'Ola', 'S1 Pro');

        $part = $this->makePart('BRK-01', 'Brake Shoe');
        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
        ]);
        $jobCard->parts()->create([
            'spare_part_id' => $part->id,
            'quantity' => 1,
            'mrp' => 50,
            'labor_charge' => 10,
            'under_warranty' => false,
            'line_total' => 60,
        ]);

        $this->actingAs($user)
            ->postJson(route('spare-parts.bulk-delete'), ['ids' => [$part->id]])
            ->assertOk()
            ->assertJsonPath('deleted_count', 0)
            ->assertJsonPath('skipped_count', 1)
            ->assertJsonPath('message', 'No records deleted, 1 skipped (linked to existing records)');

        $this->assertDatabaseHas('spare_parts', ['id' => $part->id]);
    }

    public function test_bulk_delete_requires_ids_array(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson(route('spare-parts.bulk-delete'), ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }
}