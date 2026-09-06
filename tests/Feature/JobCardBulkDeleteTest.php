<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\JobCard;
use App\Models\Payment;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class JobCardBulkDeleteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsVehicleCatalog;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makePart(string $code, string $name, int $stock): SparePart
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

    public function test_bulk_delete_soft_deletes_job_cards_and_preserves_stock_state(): void
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Owner', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $part = $this->makePart('BRK-01', 'Brake Shoe', 10);

        $withParts = $this->makeJobCard($customer, $vehicle);
        $withParts->parts()->create([
            'spare_part_id' => $part->id,
            'quantity' => 2,
            'mrp' => 50,
            'labor_charge' => 10,
            'under_warranty' => false,
            'line_total' => 120,
        ]);

        $plain = $this->makeJobCard($customer, $vehicle);

        // Stock tracking disabled - stock remains unchanged
        $this->assertEquals(10, (int) $part->fresh()->current_stock_qty);

        $response = $this->actingAs($user)->postJson(route('job-cards.bulk-delete'), [
            'ids' => [$withParts->id, $plain->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('deleted_count', 2)
            ->assertJsonPath('skipped_count', 0)
            ->assertJsonPath('message', '2 deleted');

        // Cards are soft-deleted: hidden from lists but recoverable, with
        // part rows and the stock deduction preserved for a restore.
        $this->assertSoftDeleted('job_cards', ['id' => $withParts->id]);
        $this->assertSoftDeleted('job_cards', ['id' => $plain->id]);
        $this->assertDatabaseHas('job_card_parts', ['job_card_id' => $withParts->id]);

        // Stock tracking disabled - soft delete doesn't affect stock
        $this->assertEquals(10, (int) $part->fresh()->current_stock_qty);
        $this->assertEquals(0, JobCard::count());

        // Restoring both cards brings the documents back intact
        JobCard::withTrashed()->whereIn('id', [$withParts->id, $plain->id])->restore();
        $this->assertEquals(2, JobCard::count());
        $this->assertCount(1, $withParts->fresh()->parts);
    }

    public function test_bulk_delete_job_card_keeps_payment_history_intact(): void
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Owner', 'mobile' => '9000000002', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-5678', 'Ola', 'S1 Pro');

        $jobCard = $this->makeJobCard($customer, $vehicle);
        $jobCard->update(['grand_total' => 500, 'gst_applicable' => false]);
        Payment::create([
            'payable_id' => $jobCard->id,
            'payable_type' => JobCard::class,
            'amount' => 200,
            'payment_mode' => 'cash',
            'payment_date' => '2026-08-03',
        ]);

        $this->actingAs($user)
            ->postJson(route('job-cards.bulk-delete'), ['ids' => [$jobCard->id]])
            ->assertOk()
            ->assertJsonPath('deleted_count', 1)
            ->assertJsonPath('skipped_count', 0);

        $this->assertSoftDeleted('job_cards', ['id' => $jobCard->id]);
        $this->assertDatabaseHas('payments', ['payable_id' => $jobCard->id, 'payable_type' => JobCard::class]);
    }

    public function test_bulk_delete_requires_ids_array(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson(route('job-cards.bulk-delete'), ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }
}
