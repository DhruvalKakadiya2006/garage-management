<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleCatalogTest extends TestCase
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

    public function test_catalog_page_renders_brands_and_their_models()
    {
        $user = $this->makeUser();
        $brand = $this->makeBrand('Ola');
        VehicleModel::create(['vehicle_brand_id' => $brand->id, 'name' => 'S1 Pro', 'battery_type' => 'lithium-ion']);
        VehicleModel::create(['vehicle_brand_id' => $brand->id, 'name' => 'S1 Air']);

        $this->actingAs($user)
            ->get(route('vehicle-catalog.index'))
            ->assertOk()
            ->assertSee('Vehicle Catalog')
            ->assertSee('Ola')
            ->assertSee('S1 Pro')
            ->assertSee('S1 Air');
    }

    public function test_brand_can_be_created()
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('vehicle-brands.store'), ['name' => 'Ather'])
            ->assertRedirect(route('vehicle-catalog.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicle_brands', ['name' => 'Ather']);
    }

    public function test_brand_name_must_be_unique()
    {
        $user = $this->makeUser();
        $this->makeBrand('Ola');

        $this->actingAs($user)
            ->post(route('vehicle-brands.store'), ['name' => 'Ola'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('vehicle_brands', 1);
    }

    public function test_brand_can_be_updated()
    {
        $user = $this->makeUser();
        $brand = $this->makeBrand('Ola');

        $this->actingAs($user)
            ->put(route('vehicle-brands.update', $brand), ['name' => 'Ola Electric'])
            ->assertRedirect(route('vehicle-catalog.index'));

        $this->assertDatabaseHas('vehicle_brands', ['name' => 'Ola Electric']);
    }

    public function test_brand_with_models_cannot_be_deleted()
    {
        $user = $this->makeUser();
        $brand = $this->makeBrand('Ola');
        VehicleModel::create(['vehicle_brand_id' => $brand->id, 'name' => 'S1 Pro']);

        $this->actingAs($user)
            ->delete(route('vehicle-brands.destroy', $brand))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('vehicle_brands', ['id' => $brand->id]);
        $this->assertDatabaseHas('vehicle_models', ['vehicle_brand_id' => $brand->id, 'name' => 'S1 Pro']);
    }

    public function test_empty_brand_can_be_deleted()
    {
        $user = $this->makeUser();
        $brand = $this->makeBrand('Temp Brand');

        $this->actingAs($user)
            ->delete(route('vehicle-brands.destroy', $brand))
            ->assertRedirect(route('vehicle-catalog.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('vehicle_brands', ['id' => $brand->id]);
    }

    public function test_model_can_be_created_under_brand()
    {
        $user = $this->makeUser();
        $brand = $this->makeBrand('Ather');

        $this->actingAs($user)
            ->post(route('vehicle-models.store'), [
                'vehicle_brand_id' => $brand->id,
                'name' => '450X',
                'battery_type' => 'lithium-ion',
            ])
            ->assertRedirect(route('vehicle-catalog.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicle_models', ['vehicle_brand_id' => $brand->id, 'name' => '450X', 'battery_type' => 'lithium-ion']);
    }

    public function test_duplicate_model_name_under_same_brand_is_rejected()
    {
        $user = $this->makeUser();
        $brand = $this->makeBrand('Ather');
        VehicleModel::create(['vehicle_brand_id' => $brand->id, 'name' => '450X']);

        $this->actingAs($user)
            ->post(route('vehicle-models.store'), ['vehicle_brand_id' => $brand->id, 'name' => '450X'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('vehicle_models', 1);
    }

    public function test_same_model_name_allowed_under_different_brand()
    {
        $user = $this->makeUser();
        $brandA = $this->makeBrand('Ola');
        $brandB = $this->makeBrand('Ather');
        VehicleModel::create(['vehicle_brand_id' => $brandA->id, 'name' => 'S1']);

        $this->actingAs($user)
            ->post(route('vehicle-models.store'), ['vehicle_brand_id' => $brandB->id, 'name' => 'S1'])
            ->assertRedirect(route('vehicle-catalog.index'));

        $this->assertDatabaseCount('vehicle_models', 2);
    }

    public function test_model_can_be_updated()
    {
        $user = $this->makeUser();
        $brand = $this->makeBrand('Ather');
        $model = VehicleModel::create(['vehicle_brand_id' => $brand->id, 'name' => '450X']);

        $this->actingAs($user)
            ->put(route('vehicle-models.update', $model), [
                'vehicle_brand_id' => $brand->id,
                'name' => '450S',
                'battery_type' => 'lithium-ion',
            ])
            ->assertRedirect(route('vehicle-catalog.index'));

        $this->assertDatabaseHas('vehicle_models', ['id' => $model->id, 'name' => '450S', 'battery_type' => 'lithium-ion']);
    }

    public function test_model_can_be_deleted()
    {
        $user = $this->makeUser();
        $brand = $this->makeBrand('Ather');
        $model = VehicleModel::create(['vehicle_brand_id' => $brand->id, 'name' => '450X']);

        $this->actingAs($user)
            ->delete(route('vehicle-models.destroy', $model))
            ->assertRedirect(route('vehicle-catalog.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('vehicle_models', ['id' => $model->id]);
    }
}
