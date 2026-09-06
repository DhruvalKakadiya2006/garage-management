<?php

namespace Tests\Unit;

use App\Http\Requests\FilterCustomersRequest;
use Tests\TestCase;

class FilterCustomersRequestTest extends TestCase
{
    /**
     * Test that all valid filter parameters pass validation
     */
    public function test_valid_parameters_pass_validation(): void
    {
        $request = new FilterCustomersRequest;

        $data = [
            'search' => 'John Doe',
            'outstanding' => 'has_outstanding',
            'sort' => 'display_name',
            'direction' => 'asc',
            'per_page' => 25,
        ];

        $validator = $this->app['validator']->make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    /**
     * Test that search parameter accepts strings up to 255 characters
     */
    public function test_search_accepts_valid_string(): void
    {
        $request = new FilterCustomersRequest;

        $data = ['search' => str_repeat('a', 255)];

        $validator = $this->app['validator']->make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    /**
     * Test that search parameter rejects strings longer than 255 characters
     */
    public function test_search_rejects_string_longer_than_255(): void
    {
        $request = new FilterCustomersRequest;

        $data = ['search' => str_repeat('a', 256)];

        $validator = $this->app['validator']->make($data, $request->rules());

        $this->assertTrue($validator->fails());
    }

    /**
     * Test that outstanding filter only accepts valid values
     */
    public function test_outstanding_filter_accepts_valid_values(): void
    {
        $request = new FilterCustomersRequest;

        $validValues = ['all', 'has_outstanding', 'no_outstanding'];

        foreach ($validValues as $value) {
            $validator = $this->app['validator']->make(['outstanding' => $value], $request->rules());
            $this->assertTrue($validator->passes(), "Failed for value: {$value}");
        }
    }

    /**
     * Test that outstanding filter rejects invalid values
     */
    public function test_outstanding_filter_rejects_invalid_values(): void
    {
        $request = new FilterCustomersRequest;

        $data = ['outstanding' => 'invalid_value'];

        $validator = $this->app['validator']->make($data, $request->rules());

        $this->assertTrue($validator->fails());
    }

    /**
     * Test that sort parameter only accepts valid column names
     */
    public function test_sort_accepts_valid_columns(): void
    {
        $request = new FilterCustomersRequest;

        $validColumns = ['display_name', 'outstanding', 'total_visits', 'created_at'];

        foreach ($validColumns as $column) {
            $validator = $this->app['validator']->make(['sort' => $column], $request->rules());
            $this->assertTrue($validator->passes(), "Failed for column: {$column}");
        }
    }

    /**
     * Test that sort parameter rejects invalid column names
     */
    public function test_sort_rejects_invalid_columns(): void
    {
        $request = new FilterCustomersRequest;

        $data = ['sort' => 'invalid_column'];

        $validator = $this->app['validator']->make($data, $request->rules());

        $this->assertTrue($validator->fails());
    }

    /**
     * Test that direction parameter only accepts asc or desc
     */
    public function test_direction_accepts_valid_values(): void
    {
        $request = new FilterCustomersRequest;

        $validDirections = ['asc', 'desc'];

        foreach ($validDirections as $direction) {
            $validator = $this->app['validator']->make(['direction' => $direction], $request->rules());
            $this->assertTrue($validator->passes(), "Failed for direction: {$direction}");
        }
    }

    /**
     * Test that direction parameter rejects invalid values
     */
    public function test_direction_rejects_invalid_values(): void
    {
        $request = new FilterCustomersRequest;

        $data = ['direction' => 'invalid'];

        $validator = $this->app['validator']->make($data, $request->rules());

        $this->assertTrue($validator->fails());
    }

    /**
     * Test that per_page accepts valid integer range
     */
    public function test_per_page_accepts_valid_range(): void
    {
        $request = new FilterCustomersRequest;

        $validValues = [5, 25, 50, 100];

        foreach ($validValues as $value) {
            $validator = $this->app['validator']->make(['per_page' => $value], $request->rules());
            $this->assertTrue($validator->passes(), "Failed for per_page: {$value}");
        }
    }

    /**
     * Test that per_page rejects values below minimum
     */
    public function test_per_page_rejects_below_minimum(): void
    {
        $request = new FilterCustomersRequest;

        $data = ['per_page' => 4];

        $validator = $this->app['validator']->make($data, $request->rules());

        $this->assertTrue($validator->fails());
    }

    /**
     * Test that per_page rejects values above maximum
     */
    public function test_per_page_rejects_above_maximum(): void
    {
        $request = new FilterCustomersRequest;

        $data = ['per_page' => 101];

        $validator = $this->app['validator']->make($data, $request->rules());

        $this->assertTrue($validator->fails());
    }

    /**
     * Test that per_page rejects non-integer values
     */
    public function test_per_page_rejects_non_integer(): void
    {
        $request = new FilterCustomersRequest;

        $data = ['per_page' => 'not_a_number'];

        $validator = $this->app['validator']->make($data, $request->rules());

        $this->assertTrue($validator->fails());
    }

    /**
     * Test that all parameters are nullable
     */
    public function test_all_parameters_are_nullable(): void
    {
        $request = new FilterCustomersRequest;

        $data = [];

        $validator = $this->app['validator']->make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    /**
     * Test that custom error messages are defined
     */
    public function test_custom_error_messages_are_defined(): void
    {
        $request = new FilterCustomersRequest;

        $messages = $request->messages();

        $this->assertArrayHasKey('outstanding.in', $messages);
        $this->assertArrayHasKey('sort.in', $messages);
        $this->assertArrayHasKey('direction.in', $messages);

        $this->assertEquals('Invalid outstanding balance filter.', $messages['outstanding.in']);
        $this->assertEquals('Invalid sort column.', $messages['sort.in']);
        $this->assertEquals('Sort direction must be asc or desc.', $messages['direction.in']);
    }

    /**
     * Test that authorize method returns true
     */
    public function test_authorize_returns_true(): void
    {
        $request = new FilterCustomersRequest;

        $this->assertTrue($request->authorize());
    }
}
