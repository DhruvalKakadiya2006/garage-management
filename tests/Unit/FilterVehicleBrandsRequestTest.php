<?php

namespace Tests\Unit;

use App\Http\Requests\FilterVehicleBrandsRequest;
use Tests\TestCase;

class FilterVehicleBrandsRequestTest extends TestCase
{
    /**
     * Test that all valid filter parameters pass validation
     */
    public function test_valid_parameters_pass_validation(): void
    {
        $request = new FilterVehicleBrandsRequest;

        $data = [
            'model_count' => 'has_models',
            'sort' => 'name',
            'direction' => 'asc',
            'per_page' => 25,
        ];

        $validator = validator($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    /**
     * Test that model_count filter only accepts valid values
     */
    public function test_model_count_filter_accepts_valid_values(): void
    {
        $request = new FilterVehicleBrandsRequest;

        $validValues = ['all', 'has_models', 'no_models'];

        foreach ($validValues as $value) {
            $validator = validator(['model_count' => $value], $request->rules());
            $this->assertTrue($validator->passes(), "Failed for value: {$value}");
        }
    }

    /**
     * Test that model_count filter rejects invalid values
     */
    public function test_model_count_filter_rejects_invalid_values(): void
    {
        $request = new FilterVehicleBrandsRequest;

        $data = ['model_count' => 'invalid_value'];

        $validator = validator($data, $request->rules());

        $this->assertTrue($validator->fails());
    }

    /**
     * Test that sort parameter only accepts valid column names
     */
    public function test_sort_accepts_valid_columns(): void
    {
        $request = new FilterVehicleBrandsRequest;

        $validColumns = ['name', 'models_count'];

        foreach ($validColumns as $column) {
            $validator = validator(['sort' => $column], $request->rules());
            $this->assertTrue($validator->passes(), "Failed for column: {$column}");
        }
    }

    /**
     * Test that sort parameter rejects invalid column names
     */
    public function test_sort_rejects_invalid_columns(): void
    {
        $request = new FilterVehicleBrandsRequest;

        $data = ['sort' => 'invalid_column'];

        $validator = validator($data, $request->rules());

        $this->assertTrue($validator->fails());
    }

    /**
     * Test that direction parameter only accepts asc or desc
     */
    public function test_direction_accepts_valid_values(): void
    {
        $request = new FilterVehicleBrandsRequest;

        $validDirections = ['asc', 'desc'];

        foreach ($validDirections as $direction) {
            $validator = validator(['direction' => $direction], $request->rules());
            $this->assertTrue($validator->passes(), "Failed for direction: {$direction}");
        }
    }

    /**
     * Test that direction parameter rejects invalid values
     */
    public function test_direction_rejects_invalid_values(): void
    {
        $request = new FilterVehicleBrandsRequest;

        $data = ['direction' => 'invalid'];

        $validator = validator($data, $request->rules());

        $this->assertTrue($validator->fails());
    }

    /**
     * Test that per_page accepts valid integer range
     */
    public function test_per_page_accepts_valid_range(): void
    {
        $request = new FilterVehicleBrandsRequest;

        $validValues = [5, 25, 50, 100];

        foreach ($validValues as $value) {
            $validator = validator(['per_page' => $value], $request->rules());
            $this->assertTrue($validator->passes(), "Failed for per_page: {$value}");
        }
    }

    /**
     * Test that per_page rejects values below minimum
     */
    public function test_per_page_rejects_below_minimum(): void
    {
        $request = new FilterVehicleBrandsRequest;

        $data = ['per_page' => 4];

        $validator = validator($data, $request->rules());

        $this->assertTrue($validator->fails());
    }

    /**
     * Test that per_page rejects values above maximum
     */
    public function test_per_page_rejects_above_maximum(): void
    {
        $request = new FilterVehicleBrandsRequest;

        $data = ['per_page' => 101];

        $validator = validator($data, $request->rules());

        $this->assertTrue($validator->fails());
    }

    /**
     * Test that per_page rejects non-integer values
     */
    public function test_per_page_rejects_non_integer(): void
    {
        $request = new FilterVehicleBrandsRequest;

        $data = ['per_page' => 'not_a_number'];

        $validator = validator($data, $request->rules());

        $this->assertTrue($validator->fails());
    }

    /**
     * Test that all parameters are nullable
     */
    public function test_all_parameters_are_nullable(): void
    {
        $request = new FilterVehicleBrandsRequest;

        $data = [];

        $validator = validator($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    /**
     * Test that custom error messages are defined
     */
    public function test_custom_error_messages_are_defined(): void
    {
        $request = new FilterVehicleBrandsRequest;

        $messages = $request->messages();

        $this->assertArrayHasKey('model_count.in', $messages);
        $this->assertArrayHasKey('sort.in', $messages);
        $this->assertArrayHasKey('direction.in', $messages);

        $this->assertEquals('Invalid model count filter.', $messages['model_count.in']);
        $this->assertEquals('Invalid sort column.', $messages['sort.in']);
        $this->assertEquals('Sort direction must be asc or desc.', $messages['direction.in']);
    }

    /**
     * Test that authorize method returns true
     */
    public function test_authorize_returns_true(): void
    {
        $request = new FilterVehicleBrandsRequest;

        $this->assertTrue($request->authorize());
    }
}
