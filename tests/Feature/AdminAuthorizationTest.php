<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\VehicleBrand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_user_cannot_delete_protected_records(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);

        $this->actingAs($staff)
            ->delete(route('customers.destroy', $customer))
            ->assertForbidden();

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_staff_user_cannot_bulk_delete_or_edit_payments(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->postJson(route('payments.bulk-delete'), ['ids' => [1, 2]])
            ->assertForbidden();

        $jobCardResponse = $this->actingAs($staff)->postJson(route('job-cards.bulk-delete'), ['ids' => [1]]);
        $jobCardResponse->assertForbidden();
    }

    public function test_admin_gate_distinguishes_staff_from_admin(): void
    {
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->create();

        $this->assertFalse($staff->can('admin'));
        $this->assertTrue($admin->can('admin'));
        $this->assertSame(false, $staff->is_admin);
        $this->assertSame(true, $admin->is_admin);
    }

    public function test_staff_user_cannot_bulk_delete_masters(): void
    {
        $staff = User::factory()->staff()->create();
        $brand = VehicleBrand::create(['name' => 'Ola Electric']);

        $this->actingAs($staff)
            ->postJson(route('vehicle-brands.bulk-delete'), ['ids' => [$brand->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('vehicle_brands', ['id' => $brand->id]);
    }
}
