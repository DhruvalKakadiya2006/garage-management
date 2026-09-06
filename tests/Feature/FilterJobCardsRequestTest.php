<?php

namespace Tests\Feature;

use App\Http\Requests\FilterJobCardsRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FilterJobCardsRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new FilterJobCardsRequest;

        return Validator::make($data, $request->rules(), $request->messages());
    }

    public function test_search_validation_passes_with_valid_string(): void
    {
        $validator = $this->validate(['search' => 'test search']);
        $this->assertFalse($validator->fails());
    }

    public function test_search_validation_fails_when_exceeds_max_length(): void
    {
        $validator = $this->validate(['search' => str_repeat('a', 256)]);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('search', $validator->errors()->toArray());
    }

    public function test_date_range_validation_passes_with_both_dates(): void
    {
        $validator = $this->validate([
            'from_date' => '2024-01-01',
            'to_date' => '2024-01-31',
        ]);
        $this->assertFalse($validator->fails());
    }

    public function test_date_range_validation_fails_when_from_date_without_to_date(): void
    {
        $validator = $this->validate(['from_date' => '2024-01-01']);
        $this->assertTrue($validator->fails());
        // Laravel evaluates before_or_equal before required_with when comparing with null
        $this->assertArrayHasKey('from_date', $validator->errors()->toArray());
    }

    public function test_date_range_validation_fails_when_to_date_without_from_date(): void
    {
        $validator = $this->validate(['to_date' => '2024-01-31']);
        // Laravel's required_with on to_date triggers when from_date is missing
        $this->assertTrue($validator->fails());
    }

    public function test_date_range_validation_fails_when_to_date_is_before_from_date(): void
    {
        $validator = $this->validate([
            'from_date' => '2024-01-31',
            'to_date' => '2024-01-01',
        ]);
        $this->assertTrue($validator->fails());
        $this->assertEquals(
            'From Date must be before or equal to To Date.',
            $validator->errors()->first('from_date')
        );
    }

    public function test_payment_status_validation_passes_with_valid_values(): void
    {
        $validStatuses = ['all', 'paid', 'partially_paid', 'unbilled', 'unpaid'];

        foreach ($validStatuses as $status) {
            $validator = $this->validate(['payment_status' => $status]);
            $this->assertFalse($validator->fails(), "Failed for status: {$status}");
        }
    }

    public function test_payment_status_validation_fails_with_invalid_value(): void
    {
        $validator = $this->validate(['payment_status' => 'invalid_status']);
        $this->assertTrue($validator->fails());
        $this->assertEquals(
            'Invalid payment status filter.',
            $validator->errors()->first('payment_status')
        );
    }

    public function test_sort_validation_passes_with_valid_columns(): void
    {
        $validColumns = ['job_date', 'status', 'assigned_technician_id', 'grand_total', 'payment_status_label'];

        foreach ($validColumns as $column) {
            $validator = $this->validate(['sort' => $column]);
            $this->assertFalse($validator->fails(), "Failed for column: {$column}");
        }
    }

    public function test_sort_validation_fails_with_invalid_column(): void
    {
        $validator = $this->validate(['sort' => 'invalid_column']);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('sort', $validator->errors()->toArray());
    }

    public function test_direction_validation_passes_with_valid_directions(): void
    {
        $validator = $this->validate(['direction' => 'asc']);
        $this->assertFalse($validator->fails());

        $validator = $this->validate(['direction' => 'desc']);
        $this->assertFalse($validator->fails());
    }

    public function test_direction_validation_fails_with_invalid_direction(): void
    {
        $validator = $this->validate(['direction' => 'invalid']);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('direction', $validator->errors()->toArray());
    }

    public function test_per_page_validation_passes_with_valid_range(): void
    {
        $validator = $this->validate(['per_page' => 15]);
        $this->assertFalse($validator->fails());

        $validator = $this->validate(['per_page' => 5]);
        $this->assertFalse($validator->fails());

        $validator = $this->validate(['per_page' => 100]);
        $this->assertFalse($validator->fails());
    }

    public function test_per_page_validation_fails_when_below_minimum(): void
    {
        $validator = $this->validate(['per_page' => 4]);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }

    public function test_per_page_validation_fails_when_above_maximum(): void
    {
        $validator = $this->validate(['per_page' => 101]);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }

    public function test_all_nullable_fields_pass_when_empty(): void
    {
        $validator = $this->validate([]);
        $this->assertFalse($validator->fails());
    }

    public function test_authorization_always_returns_true(): void
    {
        $request = new FilterJobCardsRequest;
        $this->assertTrue($request->authorize());
    }
}
