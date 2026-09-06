<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\FilterEmployeesRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FilterEmployeesRequestTest extends TestCase
{
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new FilterEmployeesRequest;

        return Validator::make($data, $request->rules(), $request->messages());
    }

    public function test_valid_active_status_passes_validation(): void
    {
        $validStatuses = ['all', 'active_only', 'inactive_only'];

        foreach ($validStatuses as $status) {
            $validator = $this->validate(['active' => $status]);
            $this->assertFalse($validator->fails(), "Active status '{$status}' should be valid");
        }
    }

    public function test_invalid_active_status_fails_validation(): void
    {
        $validator = $this->validate([
            'active' => 'invalid_status',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('active', $validator->errors()->toArray());
        $this->assertStringContainsString('Invalid active status filter', $validator->errors()->first('active'));
    }

    public function test_valid_role_passes_validation(): void
    {
        $validRoles = array_merge(['all'], array_keys(config('garage.option_lists.employee_role')));

        foreach ($validRoles as $role) {
            $validator = $this->validate(['role' => $role]);
            $this->assertFalse($validator->fails(), "Role '{$role}' should be valid");
        }
    }

    public function test_invalid_role_fails_validation(): void
    {
        $validator = $this->validate([
            'role' => 'invalid_role',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('role', $validator->errors()->toArray());
        $this->assertStringContainsString('Invalid role filter', $validator->errors()->first('role'));
    }

    public function test_valid_specialization_passes_validation(): void
    {
        $validSpecializations = array_merge(['all'], array_keys(config('garage.option_lists.employee_specialization')));

        foreach ($validSpecializations as $specialization) {
            $validator = $this->validate(['specialization' => $specialization]);
            $this->assertFalse($validator->fails(), "Specialization '{$specialization}' should be valid");
        }
    }

    public function test_invalid_specialization_fails_validation(): void
    {
        $validator = $this->validate([
            'specialization' => 'invalid_specialization',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('specialization', $validator->errors()->toArray());
        $this->assertStringContainsString('Invalid specialization filter', $validator->errors()->first('specialization'));
    }

    public function test_valid_sort_columns_pass_validation(): void
    {
        $validColumns = ['name', 'role', 'specialization', 'active'];

        foreach ($validColumns as $column) {
            $validator = $this->validate(['sort' => $column]);
            $this->assertFalse($validator->fails(), "Sort column '{$column}' should be valid");
        }
    }

    public function test_invalid_sort_column_fails_validation(): void
    {
        $validator = $this->validate([
            'sort' => 'invalid_column',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('sort', $validator->errors()->toArray());
        $this->assertStringContainsString('Invalid sort column', $validator->errors()->first('sort'));
    }

    public function test_valid_sort_direction_passes_validation(): void
    {
        $validator = $this->validate(['direction' => 'asc']);
        $this->assertFalse($validator->fails());

        $validator = $this->validate(['direction' => 'desc']);
        $this->assertFalse($validator->fails());
    }

    public function test_invalid_sort_direction_fails_validation(): void
    {
        $validator = $this->validate([
            'direction' => 'invalid',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('direction', $validator->errors()->toArray());
        $this->assertStringContainsString('Sort direction must be asc or desc', $validator->errors()->first('direction'));
    }

    public function test_per_page_within_range_passes_validation(): void
    {
        $validator = $this->validate(['per_page' => 15]);
        $this->assertFalse($validator->fails());

        $validator = $this->validate(['per_page' => 5]);
        $this->assertFalse($validator->fails());

        $validator = $this->validate(['per_page' => 100]);
        $this->assertFalse($validator->fails());
    }

    public function test_per_page_below_minimum_fails_validation(): void
    {
        $validator = $this->validate(['per_page' => 4]);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }

    public function test_per_page_above_maximum_fails_validation(): void
    {
        $validator = $this->validate(['per_page' => 101]);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }

    public function test_empty_request_passes_validation(): void
    {
        $validator = $this->validate([]);
        $this->assertFalse($validator->fails());
    }

    public function test_combined_valid_filters_pass_validation(): void
    {
        $validator = $this->validate([
            'active' => 'active_only',
            'role' => 'technician',
            'specialization' => 'battery',
            'sort' => 'name',
            'direction' => 'asc',
            'per_page' => 20,
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_authorize_returns_true(): void
    {
        $request = new FilterEmployeesRequest;
        $this->assertTrue($request->authorize());
    }
}
