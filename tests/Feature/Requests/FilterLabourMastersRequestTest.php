<?php

namespace Tests\Feature\Requests;

use App\Http\Requests\FilterLabourMastersRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FilterLabourMastersRequestTest extends TestCase
{
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new FilterLabourMastersRequest;

        return Validator::make($data, $request->rules(), $request->messages());
    }

    public function test_authorize_returns_true(): void
    {
        $request = new FilterLabourMastersRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_valid_search_passes_validation(): void
    {
        $validator = $this->validate(['search' => 'Oil Change']);
        $this->assertFalse($validator->fails());
    }

    public function test_search_exceeding_max_length_fails(): void
    {
        $validator = $this->validate(['search' => str_repeat('a', 256)]);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('search', $validator->errors()->toArray());
    }

    public function test_valid_taxable_values_pass_validation(): void
    {
        $validTaxableValues = ['all', 'taxable', 'non_taxable'];

        foreach ($validTaxableValues as $value) {
            $validator = $this->validate(['taxable' => $value]);
            $this->assertFalse($validator->fails(), "Taxable value '{$value}' should be valid");
        }
    }

    public function test_invalid_taxable_value_fails_validation(): void
    {
        $validator = $this->validate(['taxable' => 'invalid_value']);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('taxable', $validator->errors()->toArray());
        $this->assertEquals('Invalid taxability filter.', $validator->errors()->first('taxable'));
    }

    public function test_valid_sort_columns_pass_validation(): void
    {
        $validColumns = ['service_name', 'rate', 'taxable'];

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
            'search' => 'Engine Repair',
            'taxable' => 'taxable',
            'sort' => 'service_name',
            'direction' => 'asc',
            'per_page' => 20,
        ]);

        $this->assertFalse($validator->fails());
    }
}
