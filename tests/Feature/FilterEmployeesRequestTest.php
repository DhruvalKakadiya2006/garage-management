<?php

namespace Tests\Feature;

use App\Http\Requests\FilterEmployeesRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FilterEmployeesRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test valid active filter values pass validation
     */
    public function test_valid_active_filter_values_pass_validation(): void
    {
        $request = new FilterEmployeesRequest;

        $validValues = ['all', 'active_only', 'inactive_only'];

        foreach ($validValues as $value) {
            $validator = Validator::make(
                ['active' => $value],
                $request->rules()
            );

            $this->assertFalse(
                $validator->fails(),
                "Active filter value '{$value}' should be valid"
            );
        }
    }

    /**
     * Test invalid active filter value fails validation
     */
    public function test_invalid_active_filter_value_fails_validation(): void
    {
        $request = new FilterEmployeesRequest;

        $validator = Validator::make(
            ['active' => 'invalid_value'],
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('active', $validator->errors()->toArray());
    }

    /**
     * Test valid role filter values pass validation
     */
    public function test_valid_role_filter_values_pass_validation(): void
    {
        $request = new FilterEmployeesRequest;

        $validRoles = array_merge(['all'], array_keys(config('garage.option_lists.employee_role')));

        foreach ($validRoles as $role) {
            $validator = Validator::make(
                ['role' => $role],
                $request->rules()
            );

            $this->assertFalse(
                $validator->fails(),
                "Role filter value '{$role}' should be valid"
            );
        }
    }

    /**
     * Test invalid role filter value fails validation
     */
    public function test_invalid_role_filter_value_fails_validation(): void
    {
        $request = new FilterEmployeesRequest;

        $validator = Validator::make(
            ['role' => 'invalid_role'],
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('role', $validator->errors()->toArray());
    }

    /**
     * Test valid specialization filter values pass validation
     */
    public function test_valid_specialization_filter_values_pass_validation(): void
    {
        $request = new FilterEmployeesRequest;

        $validSpecializations = array_merge(['all'], array_keys(config('garage.option_lists.employee_specialization')));

        foreach ($validSpecializations as $specialization) {
            $validator = Validator::make(
                ['specialization' => $specialization],
                $request->rules()
            );

            $this->assertFalse(
                $validator->fails(),
                "Specialization filter value '{$specialization}' should be valid"
            );
        }
    }

    /**
     * Test invalid specialization filter value fails validation
     */
    public function test_invalid_specialization_filter_value_fails_validation(): void
    {
        $request = new FilterEmployeesRequest;

        $validator = Validator::make(
            ['specialization' => 'invalid_specialization'],
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('specialization', $validator->errors()->toArray());
    }

    /**
     * Test valid sort column values pass validation
     */
    public function test_valid_sort_column_values_pass_validation(): void
    {
        $request = new FilterEmployeesRequest;

        $validSortColumns = ['name', 'role', 'specialization', 'active'];

        foreach ($validSortColumns as $column) {
            $validator = Validator::make(
                ['sort' => $column],
                $request->rules()
            );

            $this->assertFalse(
                $validator->fails(),
                "Sort column '{$column}' should be valid"
            );
        }
    }

    /**
     * Test invalid sort column fails validation
     */
    public function test_invalid_sort_column_fails_validation(): void
    {
        $request = new FilterEmployeesRequest;

        $validator = Validator::make(
            ['sort' => 'invalid_column'],
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('sort', $validator->errors()->toArray());
    }

    /**
     * Test valid sort direction values pass validation
     */
    public function test_valid_sort_direction_values_pass_validation(): void
    {
        $request = new FilterEmployeesRequest;

        foreach (['asc', 'desc'] as $direction) {
            $validator = Validator::make(
                ['direction' => $direction],
                $request->rules()
            );

            $this->assertFalse(
                $validator->fails(),
                "Sort direction '{$direction}' should be valid"
            );
        }
    }

    /**
     * Test invalid sort direction fails validation
     */
    public function test_invalid_sort_direction_fails_validation(): void
    {
        $request = new FilterEmployeesRequest;

        $validator = Validator::make(
            ['direction' => 'invalid'],
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('direction', $validator->errors()->toArray());
    }

    /**
     * Test per_page validation with valid values
     */
    public function test_per_page_accepts_valid_values(): void
    {
        $request = new FilterEmployeesRequest;

        foreach ([5, 15, 50, 100] as $perPage) {
            $validator = Validator::make(
                ['per_page' => $perPage],
                $request->rules()
            );

            $this->assertFalse(
                $validator->fails(),
                "Per page value '{$perPage}' should be valid"
            );
        }
    }

    /**
     * Test per_page validation rejects values outside range
     */
    public function test_per_page_rejects_values_outside_range(): void
    {
        $request = new FilterEmployeesRequest;

        // Test value below minimum
        $validator = Validator::make(
            ['per_page' => 4],
            $request->rules()
        );
        $this->assertTrue($validator->fails());

        // Test value above maximum
        $validator = Validator::make(
            ['per_page' => 101],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
    }

    /**
     * Test all fields are nullable
     */
    public function test_all_fields_are_nullable(): void
    {
        $request = new FilterEmployeesRequest;

        $validator = Validator::make([], $request->rules());

        $this->assertFalse(
            $validator->fails(),
            'All fields should be nullable'
        );
    }

    /**
     * Test custom error messages are defined
     */
    public function test_custom_error_messages_are_defined(): void
    {
        $request = new FilterEmployeesRequest;
        $messages = $request->messages();

        $this->assertArrayHasKey('active.in', $messages);
        $this->assertArrayHasKey('role.in', $messages);
        $this->assertArrayHasKey('specialization.in', $messages);
        $this->assertArrayHasKey('sort.in', $messages);
        $this->assertArrayHasKey('direction.in', $messages);
    }
}
