<?php

namespace Tests\Feature\Requests;

use App\Http\Requests\FilterSparePartsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FilterSparePartsRequestTest extends TestCase
{
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new FilterSparePartsRequest;

        return Validator::make($data, $request->rules(), $request->messages());
    }

    public function test_authorize_returns_true(): void
    {
        $request = new FilterSparePartsRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_valid_search_passes_validation(): void
    {
        $validator = $this->validate(['search' => 'Brake Pad']);
        $this->assertFalse($validator->fails());
    }

    public function test_search_exceeding_max_length_fails(): void
    {
        $validator = $this->validate(['search' => str_repeat('a', 256)]);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('search', $validator->errors()->toArray());
    }

    public function test_valid_stock_status_values_pass_validation(): void
    {
        $validStatuses = ['all', 'in_stock', 'out_of_stock'];

        foreach ($validStatuses as $status) {
            $validator = $this->validate(['stock_status' => $status]);
            $this->assertFalse($validator->fails(), "Stock status '{$status}' should be valid");
        }
    }

    public function test_invalid_stock_status_fails_validation(): void
    {
        $validator = $this->validate(['stock_status' => 'invalid_status']);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('stock_status', $validator->errors()->toArray());
        $this->assertEquals('Invalid stock status filter.', $validator->errors()->first('stock_status'));
    }

    public function test_category_accepts_string_values(): void
    {
        $validator = $this->validate(['category' => 'Engine Parts']);
        $this->assertFalse($validator->fails());
    }

    public function test_valid_sort_columns_pass_validation(): void
    {
        $validColumns = ['part_name', 'stock_qty', 'mrp', 'brand', 'part_category'];

        foreach ($validColumns as $column) {
            $validator = $this->validate(['sort' => $column]);
            $this->assertFalse($validator->fails(), "Sort column '{$column}' should be valid");
        }
    }

    public function test_invalid_sort_column_fails_validation(): void
    {
        $validator = $this->validate(['sort' => 'invalid_column']);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('sort', $validator->errors()->toArray());
        $this->assertEquals('Invalid sort column.', $validator->errors()->first('sort'));
    }

    public function test_valid_direction_values_pass_validation(): void
    {
        $validator = $this->validate(['direction' => 'asc']);
        $this->assertFalse($validator->fails());

        $validator = $this->validate(['direction' => 'desc']);
        $this->assertFalse($validator->fails());
    }

    public function test_invalid_direction_fails_validation(): void
    {
        $validator = $this->validate(['direction' => 'invalid']);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('direction', $validator->errors()->toArray());
        $this->assertEquals('Sort direction must be asc or desc.', $validator->errors()->first('direction'));
    }

    public function test_per_page_within_valid_range_passes(): void
    {
        $validator = $this->validate(['per_page' => 15]);
        $this->assertFalse($validator->fails());

        $validator = $this->validate(['per_page' => 5]);
        $this->assertFalse($validator->fails());

        $validator = $this->validate(['per_page' => 100]);
        $this->assertFalse($validator->fails());
    }

    public function test_per_page_below_minimum_fails(): void
    {
        $validator = $this->validate(['per_page' => 4]);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }

    public function test_per_page_above_maximum_fails(): void
    {
        $validator = $this->validate(['per_page' => 101]);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }

    public function test_per_page_must_be_integer(): void
    {
        $validator = $this->validate(['per_page' => 'not_an_integer']);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }

    public function test_all_fields_nullable(): void
    {
        $validator = $this->validate([]);
        $this->assertFalse($validator->fails());
    }

    public function test_combined_valid_filters_pass_validation(): void
    {
        $validator = $this->validate([
            'search' => 'Oil Filter',
            'stock_status' => 'in_stock',
            'category' => 'Filters',
            'sort' => 'part_name',
            'direction' => 'asc',
            'per_page' => 20,
        ]);

        $this->assertFalse($validator->fails());
    }
}
