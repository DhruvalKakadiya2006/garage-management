<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\JobCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class EmployeeBulkDeleteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsVehicleCatalog;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeEmployee(string $name, string $mobile): Employee
    {
        return Employee::create([
            'name' => $name,
            'role' => 'technician',
            'mobile' => $mobile,
            'active' => true,
        ]);
    }

    public function test_bulk_delete_employees_removes_all_selected(): void
    {
        $user = $this->makeUser();
        $techA = $this->makeEmployee('Ramesh', '9000000001');
        $techB = $this->makeEmployee('Suresh', '9000000002');
        $techC = $this->makeEmployee('Dinesh', '9000000003');

        $response = $this->actingAs($user)->postJson(route('employees.bulk-delete'), [
            'ids' => [$techA->id, $techB->id, $techC->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('deleted_count', 3)
            ->assertJsonPath('skipped_count', 0)
            ->assertJsonPath('message', '3 deleted');

        $this->assertDatabaseMissing('employees', ['id' => $techA->id]);
        $this->assertDatabaseMissing('employees', ['id' => $techB->id]);
        $this->assertDatabaseMissing('employees', ['id' => $techC->id]);
    }

    public function test_bulk_delete_employee_assigned_to_job_card_clears_assignment(): void
    {
        $user = $this->makeUser();
        $tech = $this->makeEmployee('Ramesh', '9000000004');
        $customer = Customer::create(['first_name' => 'Owner', 'mobile' => '9000000005', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');

        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
            'assigned_technician_id' => $tech->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('employees.bulk-delete'), ['ids' => [$tech->id]])
            ->assertOk()
            ->assertJsonPath('deleted_count', 1)
            ->assertJsonPath('skipped_count', 0);

        $this->assertDatabaseMissing('employees', ['id' => $tech->id]);
        $this->assertDatabaseHas('job_cards', ['id' => $jobCard->id, 'assigned_technician_id' => null]);
    }

    public function test_bulk_delete_requires_ids_array(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson(route('employees.bulk-delete'), ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }
}