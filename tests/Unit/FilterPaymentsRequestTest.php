<?php

namespace Tests\Unit;

use App\Http\Requests\FilterPaymentsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FilterPaymentsRequestTest extends TestCase
{
    /**
     * Test that valid parameters pass validation
     */
    public function test_valid_parameters_pass_validation(): void
    {
        $request = new FilterPaymentsRequest;

        $data = [
            'payment_mode' => 'cash',
            'from_date' => '2024-01-01',
            'to_date' => '2024-01-31',
            'document_type' => 'job_card',
            'sort' => 'payment_date',
            'direction' => 'desc',
            'per_page' => 25,
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->passes());
    }

    /**
     * Test that payment_mode accepts 'all' value
     */
    public function test_payment_mode_accepts_all_value(): void
    {
        $request = new FilterPaymentsRequest;

        $data = ['payment_mode' => 'all'];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->passes());
    }

    /**
     * Test that payment_mode accepts valid config values
     */
    public function test_payment_mode_accepts_valid_config_values(): void
    {
        $request = new FilterPaymentsRequest;

        $validModes = array_keys(config('garage.option_lists.payment_mode'));

        foreach ($validModes as $mode) {
            $data = ['payment_mode' => $mode];
            $validator = Validator::make($data, $request->rules(), $request->messages());
            $this->assertTrue($validator->passes(), "Payment mode '{$mode}' should be valid");
        }
    }

    /**
     * Test that payment_mode rejects invalid values
     */
    public function test_payment_mode_rejects_invalid_values(): void
    {
        $request = new FilterPaymentsRequest;

        $data = ['payment_mode' => 'invalid_mode'];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('payment_mode', $validator->errors()->toArray());
    }

    /**
     * Test that from_date is required when to_date is present
     */
    public function test_from_date_required_with_to_date(): void
    {
        $request = new FilterPaymentsRequest;

        $data = ['to_date' => '2024-01-31'];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('from_date', $validator->errors()->toArray());
    }

    /**
     * Test that to_date is required when from_date is present
     */
    public function test_to_date_required_with_from_date(): void
    {
        $request = new FilterPaymentsRequest;

        $data = ['from_date' => '2024-01-01'];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('to_date', $validator->errors()->toArray());
    }

    /**
     * Test that from_date must be before or equal to to_date
     */
    public function test_from_date_must_be_before_or_equal_to_date(): void
    {
        $request = new FilterPaymentsRequest;

        $data = [
            'from_date' => '2024-01-31',
            'to_date' => '2024-01-01',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('from_date', $validator->errors()->toArray());
    }

    /**
     * Test that from_date and to_date can be equal
     */
    public function test_from_date_and_to_date_can_be_equal(): void
    {
        $request = new FilterPaymentsRequest;

        $data = [
            'from_date' => '2024-01-15',
            'to_date' => '2024-01-15',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->passes());
    }

    /**
     * Test that dates must be valid date format
     */
    public function test_dates_must_be_valid_format(): void
    {
        $request = new FilterPaymentsRequest;

        $data = [
            'from_date' => 'invalid-date',
            'to_date' => '2024-01-31',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('from_date', $validator->errors()->toArray());
    }

    /**
     * Test that document_type accepts valid values
     */
    public function test_document_type_accepts_valid_values(): void
    {
        $request = new FilterPaymentsRequest;

        $validTypes = ['all', 'job_card', 'counter_invoice'];

        foreach ($validTypes as $type) {
            $data = ['document_type' => $type];
            $validator = Validator::make($data, $request->rules(), $request->messages());
            $this->assertTrue($validator->passes(), "Document type '{$type}' should be valid");
        }
    }

    /**
     * Test that document_type rejects invalid values
     */
    public function test_document_type_rejects_invalid_values(): void
    {
        $request = new FilterPaymentsRequest;

        $data = ['document_type' => 'invalid_type'];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('document_type', $validator->errors()->toArray());
    }

    /**
     * Test that sort accepts valid columns
     */
    public function test_sort_accepts_valid_columns(): void
    {
        $request = new FilterPaymentsRequest;

        $validColumns = ['payment_date', 'amount', 'payment_mode'];

        foreach ($validColumns as $column) {
            $data = ['sort' => $column];
            $validator = Validator::make($data, $request->rules(), $request->messages());
            $this->assertTrue($validator->passes(), "Sort column '{$column}' should be valid");
        }
    }

    /**
     * Test that sort rejects invalid columns
     */
    public function test_sort_rejects_invalid_columns(): void
    {
        $request = new FilterPaymentsRequest;

        $data = ['sort' => 'invalid_column'];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('sort', $validator->errors()->toArray());
    }

    /**
     * Test that direction accepts valid values
     */
    public function test_direction_accepts_valid_values(): void
    {
        $request = new FilterPaymentsRequest;

        $validDirections = ['asc', 'desc'];

        foreach ($validDirections as $direction) {
            $data = ['direction' => $direction];
            $validator = Validator::make($data, $request->rules(), $request->messages());
            $this->assertTrue($validator->passes(), "Direction '{$direction}' should be valid");
        }
    }

    /**
     * Test that direction rejects invalid values
     */
    public function test_direction_rejects_invalid_values(): void
    {
        $request = new FilterPaymentsRequest;

        $data = ['direction' => 'invalid'];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('direction', $validator->errors()->toArray());
    }

    /**
     * Test that per_page accepts valid range
     */
    public function test_per_page_accepts_valid_range(): void
    {
        $request = new FilterPaymentsRequest;

        $validValues = [5, 25, 50, 100];

        foreach ($validValues as $value) {
            $data = ['per_page' => $value];
            $validator = Validator::make($data, $request->rules(), $request->messages());
            $this->assertTrue($validator->passes(), "per_page value '{$value}' should be valid");
        }
    }

    /**
     * Test that per_page rejects below minimum
     */
    public function test_per_page_rejects_below_minimum(): void
    {
        $request = new FilterPaymentsRequest;

        $data = ['per_page' => 4];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }

    /**
     * Test that per_page rejects above maximum
     */
    public function test_per_page_rejects_above_maximum(): void
    {
        $request = new FilterPaymentsRequest;

        $data = ['per_page' => 101];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }

    /**
     * Test that per_page rejects non-integer values
     */
    public function test_per_page_rejects_non_integer(): void
    {
        $request = new FilterPaymentsRequest;

        $data = ['per_page' => 'not_a_number'];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }

    /**
     * Test that all parameters are nullable
     */
    public function test_all_parameters_are_nullable(): void
    {
        $request = new FilterPaymentsRequest;

        $data = [];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->passes());
    }

    /**
     * Test that custom error messages are defined
     */
    public function test_custom_error_messages_are_defined(): void
    {
        $request = new FilterPaymentsRequest;

        $messages = $request->messages();

        $this->assertArrayHasKey('from_date.required_with', $messages);
        $this->assertArrayHasKey('to_date.required_with', $messages);
        $this->assertArrayHasKey('payment_mode.in', $messages);
        $this->assertArrayHasKey('document_type.in', $messages);

        $this->assertEquals('Both From Date and To Date must be provided.', $messages['from_date.required_with']);
        $this->assertEquals('Both From Date and To Date must be provided.', $messages['to_date.required_with']);
    }

    /**
     * Test that authorize returns true
     */
    public function test_authorize_returns_true(): void
    {
        $request = new FilterPaymentsRequest;

        $this->assertTrue($request->authorize());
    }
}
