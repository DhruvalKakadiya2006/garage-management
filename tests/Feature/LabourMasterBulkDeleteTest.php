<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\JobCard;
use App\Models\LabourMaster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class LabourMasterBulkDeleteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsVehicleCatalog;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeLabour(string $service, int $rate = 200): LabourMaster
    {
        return LabourMaster::create([
            'service_name' => $service,
            'category' => 'general',
            'standard_rate' => $rate,
            'taxable' => true,
        ]);
    }

    public function test_bulk_delete_labour_masters_skips_linked_services(): void
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Owner', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');

        $linked = $this->makeLabour('General Service');
        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
        ]);
        $jobCard->labours()->create([
            'labour_master_id' => $linked->id,
            'rate' => 200,
            'qty' => 1,
            'line_total' => 200,
        ]);

        $freeOne = $this->makeLabour('Brake Service', 150);
        $freeTwo = $this->makeLabour('Full Service', 500);

        $response = $this->actingAs($user)->postJson(route('labour-masters.bulk-delete'), [
            'ids' => [$linked->id, $freeOne->id, $freeTwo->id],
        ]);

        $response->assertOk()
            ->assertJson([
                'deleted_count' => 2,
                'skipped_count' => 1,
                'skipped_ids' => [(string) $linked->id],
            ])
            ->assertJsonPath('message', '2 deleted, 1 skipped (linked to existing records)');

        $this->assertDatabaseMissing('labour_masters', ['id' => $freeOne->id]);
        $this->assertDatabaseMissing('labour_masters', ['id' => $freeTwo->id]);
        $this->assertDatabaseHas('labour_masters', ['id' => $linked->id]);
        $this->assertDatabaseHas('job_card_labours', ['labour_master_id' => $linked->id]);
    }

    public function test_bulk_delete_with_all_linked_labour_deletes_nothing(): void
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Owner', 'mobile' => '9000000002', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-5678', 'Ola', 'S1 Pro');

        $labour = $this->makeLabour('General Service');
        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
        ]);
        $jobCard->labours()->create([
            'labour_master_id' => $labour->id,
            'rate' => 200,
            'qty' => 1,
            'line_total' => 200,
        ]);

        $this->actingAs($user)
            ->postJson(route('labour-masters.bulk-delete'), ['ids' => [$labour->id]])
            ->assertOk()
            ->assertJsonPath('deleted_count', 0)
            ->assertJsonPath('skipped_count', 1)
            ->assertJsonPath('message', 'No records deleted, 1 skipped (linked to existing records)');

        $this->assertDatabaseHas('labour_masters', ['id' => $labour->id]);
    }

    public function test_bulk_delete_requires_ids_array(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson(route('labour-masters.bulk-delete'), ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }
}