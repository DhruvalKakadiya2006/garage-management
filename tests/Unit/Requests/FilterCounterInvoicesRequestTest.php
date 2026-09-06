<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\FilterCounterInvoicesRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FilterCounterInvoicesRequestTest extends TestCase
{
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new FilterCounterInvoicesRequest;

        return Validator::make($data, $request->rules(), $request->messages());
    }

    public function test_valid_date_range_passes_validation(): void
    {
        $validator = $this->validate([
            'from_date' => '2024-01-01',
            'to_date' => '2024-01-31',
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_from_date_without_to_date_fails_validation(): void
    {
        $validator = $this->validate([
            'from_date' => '2024-01-01',
        ]);

        $this->assertTrue($validator->fails());
        // Laravel's required_with validates the missing field, not the provided one
        // So we check that validation fails when only one date is provided
    }

    public function test_to_date_without_from_date_fails_validation(): void
    {
        $validator = $this->validate([
            'to_date' => '2024-01-31',
        ]);

        $this->assertTrue($validator->fails());
        // Laravel's required_with validates the missing field, not the provided one
        // So we check that validation fails when only one date is provided
    }

    public function test_to_date_before_from_date_fails_validation(): void
    {
        $validator = $this->validate([
            'from_date' => '2024-01-31',
            'to_date' => '2024-01-01',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('from_date', $validator->errors()->toArray());
    }

    public function test_equal_dates_pass_validation(): void
    {
        $validator = $this->validate([
            'from_date' => '2024-01-15',
            'to_date' => '2024-01-15',
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_valid_payment_status_passes_validation(): void
    {
        $validStatuses = ['all', 'paid', 'partially_paid', 'unpaid'];

        foreach ($validStatuses as $status) {
            $validator = $this->validate(['payment_status' => $status]);
            $this->assertFalse($validator->fails(), "Payment status '{$status}' should be valid");
        }
    }

    public function test_invalid_payment_status_fails_validation(): void
    {
        $validator = $this->validate([
            'payment_status' => 'invalid_status',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('payment_status', $validator->errors()->toArray());
        $this->assertStringContainsString('Invalid payment status filter', $validator->errors()->first('payment_status'));
    }

    public function test_valid_customer_type_passes_validation(): void
    {
        $validTypes = ['all', 'registered', 'walk_in'];

        foreach ($validTypes as $type) {
            $validator = $this->validate(['customer_type' => $type]);
            $this->assertFalse($validator->fails(), "Customer type '{$type}' should be valid");
        }
    }

    public function test_invalid_customer_type_fails_validation(): void
    {
        $validator = $this->validate([
            'customer_type' => 'invalid_type',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('customer_type', $validator->errors()->toArray());
        $this->assertStringContainsString('Invalid customer type filter', $validator->errors()->first('customer_type'));
    }

    public function test_valid_sort_columns_pass_validation(): void
    {
        $validColumns = ['invoice_date', 'invoice_no', 'grand_total', 'payment_status_label'];

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
    }

    public function test_valid_sort_direction_passes_validation(): void
    {
        $validator = $this->validate([
            'direction' => 'asc',
        ]);
        $this->assertFalse($validator->fails());

        $validator = $this->validate([
            'direction' => 'desc',
        ]);
        $this->assertFalse($validator->fails());
    }

    public function test_invalid_sort_direction_fails_validation(): void
    {
        $validator = $this->validate([
            'direction' => 'invalid',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('direction', $validator->errors()->toArray());
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
            'from_date' => '2024-01-01',
            'to_date' => '2024-01-31',
            'payment_status' => 'paid',
            'customer_type' => 'registered',
            'sort' => 'invoice_date',
            'direction' => 'desc',
            'per_page' => 20,
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_invalid_date_format_fails_validation(): void
    {
        $validator = $this->validate([
            'from_date' => 'invalid-date',
            'to_date' => '2024-01-31',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('from_date', $validator->errors()->toArray());
    }

    public function test_authorize_returns_true(): void
    {
        $request = new FilterCounterInvoicesRequest;
        $this->assertTrue($request->authorize());
    }
}
