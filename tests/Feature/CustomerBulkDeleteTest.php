<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\JobCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class CustomerBulkDeleteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsVehicleCatalog;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeCustomer(string $name, string $mobile): Customer
    {
        return Customer::create([
            'first_name' => $name,
            'mobile' => $mobile,
            'customer_type' => 'regular',
        ]);
    }

    public function test_bulk_delete_mix_of_clean_and_linked_customers_reports_partial_skip(): void
    {
        $user = $this->makeUser();

        // Customer with history -> protected (restrict FKs from its vehicle
        // and job card, so the DB-level delete fails and it is skipped).
        $linked = $this->makeCustomer('Linked', '9000000001');
        $vehicle = $this->makeCatalogVehicle($linked, 'GJ-01-AB-0001', 'Ola', 'S1 Pro');
        JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $linked->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
        ]);

        $freeOne = $this->makeCustomer('Free One', '9000000002');
        $freeTwo = $this->makeCustomer('Free Two', '9000000003');

        $response = $this->actingAs($user)->postJson(route('customers.bulk-delete'), [
            'ids' => [$linked->id, $freeOne->id, $freeTwo->id],
        ]);

        $response->assertOk()
            ->assertJson([
                'deleted_count' => 2,
                'skipped_count' => 1,
                'skipped_ids' => [(string) $linked->id],
            ])
            ->assertJsonPath('message', '2 deleted, 1 skipped (linked to existing records)');

        $this->assertDatabaseMissing('customers', ['id' => $freeOne->id]);
        $this->assertDatabaseMissing('customers', ['id' => $freeTwo->id]);
        $this->assertDatabaseHas('customers', ['id' => $linked->id]);
        $this->assertDatabaseHas('job_cards', ['customer_id' => $linked->id]);
    }

    public function test_bulk_delete_with_all_linked_customers_deletes_nothing(): void
    {
        $user = $this->makeUser();

        $linked = $this->makeCustomer('Linked', '9000000004');
        $vehicle = $this->makeCatalogVehicle($linked, 'GJ-01-AB-0004', 'Ola', 'S1 Pro');
        JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $linked->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
        ]);

        $this->actingAs($user)
            ->postJson(route('customers.bulk-delete'), ['ids' => [$linked->id]])
            ->assertOk()
            ->assertJsonPath('deleted_count', 0)
            ->assertJsonPath('skipped_count', 1)
            ->assertJsonPath('message', 'No records deleted, 1 skipped (linked to existing records)');

        $this->assertDatabaseHas('customers', ['id' => $linked->id]);
    }

    public function test_bulk_delete_requires_ids_array(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson(route('customers.bulk-delete'), ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }
}