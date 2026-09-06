<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for customer search, filter, and sort functionality.
 *
 * Task 3.1: Implement search functionality in CustomerController
 * Validates Requirements: 1.1, 1.2, 1.4, 1.6
 */
class CustomerSearchFilterSortTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Requirement 1.1: THE Search_System SHALL accept text input for customer search queries
     */
    public function test_search_accepts_text_input(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'john']));

        $response->assertOk();
        $response->assertViewHas('customers');
    }

    /**
     * Requirement 1.2: Search both display_name and mobile using case-insensitive partial matching
     */
    public function test_search_finds_customer_by_display_name(): void
    {
        $customer1 = Customer::factory()->create(['display_name' => 'John Smith']);
        $customer2 = Customer::factory()->create(['display_name' => 'Jane Doe']);
        $customer3 = Customer::factory()->create(['display_name' => 'Bob Johnson']);

        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'john']));

        $response->assertOk();
        $customers = $response->viewData('customers');

        // Should find "John Smith" and "Bob Johnson" (contains "john")
        $this->assertCount(2, $customers);
        $this->assertTrue($customers->contains('id', $customer1->id));
        $this->assertTrue($customers->contains('id', $customer3->id));
        $this->assertFalse($customers->contains('id', $customer2->id));
    }

    /**
     * Requirement 1.2: Search both display_name and mobile using case-insensitive partial matching
     */
    public function test_search_finds_customer_by_mobile(): void
    {
        $customer1 = Customer::factory()->create([
            'display_name' => 'John Smith',
            'mobile' => '9876543210',
        ]);
        $customer2 = Customer::factory()->create([
            'display_name' => 'Jane Doe',
            'mobile' => '8765432109',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => '987654']));

        $response->assertOk();
        $customers = $response->viewData('customers');

        // Should find only customer with mobile starting with 987654
        $this->assertCount(1, $customers);
        $this->assertTrue($customers->contains('id', $customer1->id));
    }

    /**
     * Requirement 1.2: Case-insensitive search
     */
    public function test_search_is_case_insensitive(): void
    {
        $customer = Customer::factory()->create(['display_name' => 'John Smith']);

        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'JOHN']));

        $response->assertOk();
        $customers = $response->viewData('customers');

        $this->assertCount(1, $customers);
        $this->assertTrue($customers->contains('id', $customer->id));
    }

    /**
     * Requirement 1.4: Return matching Result_Sets with pagination intact
     */
    public function test_search_returns_paginated_results(): void
    {
        // Create 20 customers with "Smith" in the name
        Customer::factory()->count(20)->create(['display_name' => 'John Smith']);

        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'smith', 'per_page' => 10]));

        $response->assertOk();
        $customers = $response->viewData('customers');

        $this->assertCount(10, $customers); // First page has 10 results
        $this->assertEquals(20, $customers->total()); // Total is 20
        $this->assertEquals(2, $customers->lastPage()); // 2 pages total
    }

    /**
     * Requirement 1.6: Preserve search queries in Query_State
     */
    public function test_search_preserves_query_parameters_in_pagination(): void
    {
        Customer::factory()->count(20)->create(['display_name' => 'John Smith']);

        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'smith', 'per_page' => 10]));

        $response->assertOk();
        $customers = $response->viewData('customers');

        // Pagination links should preserve the search parameter
        $nextPageUrl = $customers->nextPageUrl();
        $this->assertStringContainsString('search=smith', $nextPageUrl);
        $this->assertStringContainsString('per_page=10', $nextPageUrl);
    }

    /**
     * Requirement 1.2: Empty search returns all customers
     */
    public function test_empty_search_returns_all_customers(): void
    {
        Customer::factory()->count(5)->create();

        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => '']));

        $response->assertOk();
        $customers = $response->viewData('customers');

        $this->assertCount(5, $customers);
    }

    /**
     * Requirement 1.2: Search with no matches returns empty result
     */
    public function test_search_with_no_matches_returns_empty(): void
    {
        Customer::factory()->count(5)->create(['display_name' => 'John Smith']);

        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'nonexistent']));

        $response->assertOk();
        $customers = $response->viewData('customers');

        $this->assertCount(0, $customers);
    }

    /**
     * Requirement 1.2: Partial matching works for any substring
     */
    public function test_search_partial_matching_works_for_middle_substring(): void
    {
        $customer = Customer::factory()->create(['display_name' => 'Christopher']);

        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'isto']));

        $response->assertOk();
        $customers = $response->viewData('customers');

        $this->assertCount(1, $customers);
        $this->assertTrue($customers->contains('id', $customer->id));
    }
}
