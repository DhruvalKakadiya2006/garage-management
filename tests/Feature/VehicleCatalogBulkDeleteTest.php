<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleCatalogBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeBrand(string $name): VehicleBrand
    {
        return VehicleBrand::create(['name' => $name]);
    }

    private function makeModel(VehicleBrand $brand, string $name): VehicleModel
    {
        return VehicleModel::create(['vehicle_brand_id' => $brand->id, 'name' => $name]);
    }

    public function test_bulk_delete_brands_skips_brands_with_models(): void
    {
        $user = $this->makeUser();

        $withModels = $this->makeBrand('Ola');
        $this->makeModel($withModels, 'S1 Pro');

        $emptyOne = $this->makeBrand('Temp One');
        $emptyTwo = $this->makeBrand('Temp Two');

        $response = $this->actingAs($user)->postJson(route('vehicle-brands.bulk-delete'), [
            'ids' => [$withModels->id, $emptyOne->id, $emptyTwo->id],
        ]);

        $response->assertOk()
            ->assertJson([
                'deleted_count' => 2,
                'skipped_count' => 1,
                'skipped_ids' => [(string) $withModels->id],
            ])
            ->assertJsonPath('message', '2 deleted, 1 skipped (linked to existing records)');

        $this->assertDatabaseMissing('vehicle_brands', ['id' => $emptyOne->id]);
        $this->assertDatabaseMissing('vehicle_brands', ['id' => $emptyTwo->id]);
        $this->assertDatabaseHas('vehicle_brands', ['id' => $withModels->id]);
        $this->assertDatabaseHas('vehicle_models', ['vehicle_brand_id' => $withModels->id, 'name' => 'S1 Pro']);
    }

    public function test_bulk_delete_brands_with_only_linked_brands_deletes_nothing(): void
    {
        $user = $this->makeUser();

        $brand = $this->makeBrand('Ola');
        $this->makeModel($brand, 'S1 Pro');

        $this->actingAs($user)
            ->postJson(route('vehicle-brands.bulk-delete'), ['ids' => [$brand->id]])
            ->assertOk()
            ->assertJsonPath('deleted_count', 0)
            ->assertJsonPath('skipped_count', 1)
            ->assertJsonPath('message', 'No records deleted, 1 skipped (linked to existing records)');

        $this->assertDatabaseHas('vehicle_brands', ['id' => $brand->id]);
    }

    public function test_bulk_delete_models_mix_of_clean_and_vehicle_linked_models(): void
    {
        $user = $this->makeUser();
        $brand = $this->makeBrand('Ather');

        $linked = $this->makeModel($brand, '450X');
        $freeOne = $this->makeModel($brand, '450S');
        $freeTwo = $this->makeModel($brand, 'Rizta');

        $customer = Customer::create([
            'first_name' => 'Owner',
            'mobile' => '9000000099',
            'customer_type' => 'regular',
        ]);
        Vehicle::create([
            'customer_id' => $customer->id,
            'registration_no' => 'GJ-01-AB-0055',
            'vehicle_model_id' => $linked->id,
        ]);

        $response = $this->actingAs($user)->postJson(route('vehicle-models.bulk-delete'), [
            'ids' => [$linked->id, $freeOne->id, $freeTwo->id],
        ]);

        $response->assertOk()
            ->assertJson([
                'deleted_count' => 2,
                'skipped_count' => 1,
                'skipped_ids' => [(string) $linked->id],
            ]);

        $this->assertDatabaseMissing('vehicle_models', ['id' => $freeOne->id]);
        $this->assertDatabaseMissing('vehicle_models', ['id' => $freeTwo->id]);
        $this->assertDatabaseHas('vehicle_models', ['id' => $linked->id]);
    }

    public function test_bulk_delete_requires_ids_array(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson(route('vehicle-brands.bulk-delete'), ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }
}
