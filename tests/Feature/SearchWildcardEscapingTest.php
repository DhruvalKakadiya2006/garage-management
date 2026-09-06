<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Test for Wildcard Escaping in Search
 *
 * Feature: advanced-search-filter-sorting, Property 2: Wildcard Escaping
 *
 * For any search query containing SQL LIKE wildcards (%, _, \),
 * the search system SHALL escape these characters and treat them
 * as literal characters, not as wildcard operators.
 *
 * Validates: Requirements 1.3, 4.3, 7.3
 *
 * This test generates multiple search scenarios with wildcard characters
 * and verifies they are treated as literal characters in database searches.
 */
class SearchWildcardEscapingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test that percent signs (%) in customer names are treated literally.
     *
     * The percent sign is a LIKE wildcard that matches any sequence of characters.
     * We must verify it's escaped and treated as a literal percent character.
     */
    public function test_searches_customer_with_percent_sign_literally(): void
    {
        // Create customer with percent sign in name
        $customer = Customer::factory()->create([
            'display_name' => '100% Auto Parts',
            'mobile' => '1234567890',
        ]);

        // Create customer that would match if % was treated as wildcard
        Customer::factory()->create([
            'display_name' => '100 Auto Parts',
            'mobile' => '9876543210',
        ]);

        // Search with percent sign should find only the exact match
        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => '100%']));

        $response->assertStatus(200);
        $response->assertSee('100% Auto Parts');
        $response->assertDontSee('100 Auto Parts');
    }

    /**
     * Test that underscores (_) in customer names are treated literally.
     *
     * The underscore is a LIKE wildcard that matches any single character.
     * We must verify it's escaped and treated as a literal underscore.
     */
    public function test_searches_customer_with_underscore_literally(): void
    {
        // Create customer with underscore in name
        $customer = Customer::factory()->create([
            'display_name' => 'John_Doe',
            'mobile' => '1111111111',
        ]);

        // Create customer that would match if _ was treated as wildcard
        Customer::factory()->create([
            'display_name' => 'John Doe', // Space instead of underscore
            'mobile' => '2222222222',
        ]);

        Customer::factory()->create([
            'display_name' => 'JohnXDoe', // Different character
            'mobile' => '3333333333',
        ]);

        // Search with underscore should find only the exact match
        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'John_Doe']));

        $response->assertStatus(200);
        $response->assertSee('John_Doe');
        $response->assertDontSee('John Doe');
        $response->assertDontSee('JohnXDoe');
    }

    /**
     * Test that backslashes (\) in customer names are treated literally.
     *
     * The backslash is the escape character itself in LIKE patterns.
     * We must verify it's properly escaped.
     */
    public function test_searches_customer_with_backslash_literally(): void
    {
        // Create customer with backslash in name (e.g., company name with path)
        $customer = Customer::factory()->create([
            'display_name' => 'C:\\Users\\Admin',
            'mobile' => '4444444444',
        ]);

        // Create customer without backslash
        Customer::factory()->create([
            'display_name' => 'C:/Users/Admin',
            'mobile' => '5555555555',
        ]);

        // Search with backslash should find only the exact match
        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'C:\\Users']));

        $response->assertStatus(200);
        $response->assertSee('C:\\Users\\Admin');
        $response->assertDontSee('C:/Users/Admin');
    }

    /**
     * Test that mobile numbers with underscores are searched literally.
     *
     * Tests wildcard escaping on the mobile field.
     */
    public function test_searches_mobile_with_underscore_literally(): void
    {
        // Create customer with underscore in mobile (formatted number)
        $customer = Customer::factory()->create([
            'display_name' => 'Test Customer',
            'mobile' => '123_456_7890',
        ]);

        // Create customer that would match if _ was treated as wildcard
        Customer::factory()->create([
            'display_name' => 'Other Customer',
            'mobile' => '1234567890', // Without underscores
        ]);

        // Search should find only the one with underscores
        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => '123_456']));

        $response->assertStatus(200);
        $response->assertSee('Test Customer');
        $response->assertDontSee('Other Customer');
    }

    /**
     * Test multiple wildcards in a single search query.
     *
     * This tests the robustness of escaping when multiple special
     * characters appear together.
     */
    public function test_searches_with_multiple_wildcards_literally(): void
    {
        // Create customer with multiple wildcards
        $customer = Customer::factory()->create([
            'display_name' => 'Test_%Value\\',
            'mobile' => '6666666666',
        ]);

        // Create customers that would match if wildcards were active
        Customer::factory()->create([
            'display_name' => 'TestABValue',
            'mobile' => '7777777777',
        ]);

        Customer::factory()->create([
            'display_name' => 'Test_XValue',
            'mobile' => '8888888888',
        ]);

        // Search with multiple wildcards should find only exact match
        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'Test_%']));

        $response->assertStatus(200);
        $response->assertSee('Test_%Value\\');
        $response->assertDontSee('TestABValue');
        $response->assertDontSee('Test_XValue');
    }

    /**
     * Property-based test: Generate multiple random wildcard patterns.
     *
     * This test simulates property-based testing by testing multiple
     * generated patterns with wildcards in various positions.
     */
    public function test_wildcard_escaping_with_random_patterns(): void
    {
        $wildcardPatterns = [
            ['name' => 'Acme%Corp', 'search' => 'Acme%', 'shouldFind' => true],
            ['name' => 'Acme Corp', 'search' => 'Acme%', 'shouldFind' => false],
            ['name' => 'User_Account', 'search' => 'User_', 'shouldFind' => true],
            ['name' => 'UserXAccount', 'search' => 'User_', 'shouldFind' => false],
            ['name' => 'Path\\To\\File', 'search' => 'Path\\To', 'shouldFind' => true],
            ['name' => 'Path/To/File', 'search' => 'Path\\To', 'shouldFind' => false],
            ['name' => '50% Off', 'search' => '50%', 'shouldFind' => true],
            ['name' => '50 Off', 'search' => '50%', 'shouldFind' => false],
            ['name' => 'Code_123_%', 'search' => 'Code_123_%', 'shouldFind' => true],
            ['name' => 'Code 123 X', 'search' => 'Code_123_%', 'shouldFind' => false],
        ];

        foreach ($wildcardPatterns as $index => $pattern) {
            // Clean up before each iteration
            Customer::query()->delete();

            // Create the test customer
            $customer = Customer::factory()->create([
                'display_name' => $pattern['name'],
                'mobile' => sprintf('900000%04d', $index),
            ]);

            // Perform search
            $response = $this->actingAs($this->user)
                ->get(route('customers.index', ['search' => $pattern['search']]));

            $response->assertStatus(200);

            if ($pattern['shouldFind']) {
                $response->assertSee($pattern['name'], false);
            } else {
                $response->assertDontSee($pattern['name'], false);
            }
        }
    }

    /**
     * Test wildcard escaping in partial matches.
     *
     * Verifies that wildcards are escaped even when performing
     * partial substring matching.
     */
    public function test_wildcard_escaping_in_partial_matches(): void
    {
        // Create customers with wildcards in different positions
        Customer::factory()->create([
            'display_name' => 'Start%Middle',
            'mobile' => '1000000001',
        ]);

        Customer::factory()->create([
            'display_name' => 'Start Middle',
            'mobile' => '1000000002',
        ]);

        // Partial search with wildcard in the middle
        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'art%Mid']));

        $response->assertStatus(200);
        $response->assertSee('Start%Middle');
        $response->assertDontSee('Start Middle');
    }

    /**
     * Test that normal search (without wildcards) still works.
     *
     * This ensures our escaping logic doesn't break normal search functionality.
     */
    public function test_normal_search_without_wildcards_works(): void
    {
        Customer::factory()->create([
            'display_name' => 'Normal Customer Name',
            'mobile' => '1234567890',
        ]);

        Customer::factory()->create([
            'display_name' => 'Another Customer',
            'mobile' => '9876543210',
        ]);

        // Normal search should work as expected
        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'Normal']));

        $response->assertStatus(200);
        $response->assertSee('Normal Customer Name');
        $response->assertDontSee('Another Customer');
    }

    /**
     * Test case-insensitive search with wildcards.
     *
     * Verifies that wildcard escaping works with case-insensitive matching.
     */
    public function test_case_insensitive_search_with_wildcards(): void
    {
        Customer::factory()->create([
            'display_name' => 'ABC_%Corporation',
            'mobile' => '1111111111',
        ]);

        // Search with different case but same wildcards
        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => 'abc_%']));

        $response->assertStatus(200);
        $response->assertSee('ABC_%Corporation');
    }

    /**
     * Test that only percent signs are escaped, not the LIKE % wrapper.
     *
     * The system adds % wrappers for partial matching (%search%),
     * but user-provided % should be escaped.
     */
    public function test_user_percent_is_escaped_but_like_wrapper_is_not(): void
    {
        // Customer with percent at the end
        Customer::factory()->create([
            'display_name' => 'Discount 25%',
            'mobile' => '2000000001',
        ]);

        // Customer without percent (different name to avoid substring match)
        Customer::factory()->create([
            'display_name' => 'Discount 25 Only',
            'mobile' => '2000000002',
        ]);

        // Search for "25%" - should find only the one with literal %
        $response = $this->actingAs($this->user)
            ->get(route('customers.index', ['search' => '25%']));

        $response->assertStatus(200);
        $response->assertSee('Discount 25%');
        $response->assertDontSee('Discount 25 Only');
    }
}
