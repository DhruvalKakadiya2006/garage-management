<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class SearchComboboxEndpointsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsVehicleCatalog;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    public function test_customer_search_requires_authentication(): void
    {
        $this->getJson(route('customers.search'))
            ->assertStatus(302)
            ->assertRedirect(route('login'));
    }

    public function test_customer_search_returns_nothing_for_empty_query(): void
    {
        $user = $this->makeUser();
        Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);

        $this->actingAs($user)
            ->getJson(route('customers.search'))
            ->assertOk()
            ->assertExactJson([]);

        $this->actingAs($user)
            ->getJson(route('customers.search', ['q' => '   ']))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_customer_search_matches_name_and_mobile_with_string_ids(): void
    {
        $user = $this->makeUser();
        Customer::create(['first_name' => 'Ravi', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        Customer::create(['first_name' => 'Priya', 'mobile' => '9888000002', 'customer_type' => 'counter']);

        $response = $this->actingAs($user)
            ->getJson(route('customers.search', ['q' => 'Ravi']))
            ->assertOk();

        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertIsString($data[0]['id']);
        $this->assertSame('Ravi', $data[0]['display_name']);
        $this->assertSame('9000000001', $data[0]['mobile']);
        $this->assertArrayHasKey('vehicles_count', $data[0]);
        $this->assertStringContainsString('9000000001', $data[0]['label']);

        // Search by mobile digit subset
        $this->actingAs($user)
            ->getJson(route('customers.search', ['q' => '9888000002']))
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_vehicles_endpoint_returns_only_selected_customers_vehicles(): void
    {
        $user = $this->makeUser();

        $customerA = Customer::create(['first_name' => 'A', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $customerB = Customer::create(['first_name' => 'B', 'mobile' => '9000000002', 'customer_type' => 'regular']);

        $vehicle = $this->makeCatalogVehicle($customerA, 'GJ-01-AB-1234', 'Ola', 'S1 Pro');
        $this->makeCatalogVehicle($customerB, 'GJ-09-CD-5678', 'Ather', '450X');

        $data = $this->actingAs($user)
            ->getJson(route('customers.vehicles', $customerA))
            ->assertOk()
            ->json();

        $this->assertCount(1, $data);
        $this->assertIsString($data[0]['id']);
        $this->assertSame((string) $customerA->id, $data[0]['customer_id']);
        $this->assertSame('GJ-01-AB-1234', $data[0]['registration_no']);
        $this->assertArrayHasKey('is_non_rto', $data[0]);
        $this->assertSame('Ola', $data[0]['brand']);
        $this->assertSame('S1 Pro', $data[0]['model']);
        $this->assertStringContainsString('GJ-01-AB-1234', $data[0]['label']);
    }

    public function test_vehicles_endpoint_returns_empty_for_customer_without_vehicles(): void
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'A', 'mobile' => '9000000001', 'customer_type' => 'regular']);

        $this->actingAs($user)
            ->getJson(route('customers.vehicles', $customer))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_spare_part_search_returns_nothing_for_empty_query(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)
            ->getJson(route('spare-parts.search'))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_spare_part_search_matches_name_and_code_with_pricing(): void
    {
        $user = $this->makeUser();

        SparePart::create([
            'part_name' => 'Brake Shoe',
            'part_code' => 'BRK-01',
            'category' => 'brake',
            'unit' => 'pcs',
            'purchase_price' => 30,
            'mrp' => 50,
            'labor_charge' => 10,
            'gst_rate' => 18,
            'current_stock_qty' => 20,
            'reorder_level' => 5,
        ]);

        $data = $this->actingAs($user)
            ->getJson(route('spare-parts.search', ['q' => 'BRK']))
            ->assertOk()
            ->json();

        $this->assertCount(1, $data);
        $this->assertIsString($data[0]['id']);
        $this->assertSame('Brake Shoe', $data[0]['part_name']);
        $this->assertSame('BRK-01', $data[0]['part_code']);
        $this->assertSame(50.0, (float) $data[0]['mrp']);
        $this->assertSame(10.0, (float) $data[0]['labor_charge']);
        $this->assertSame(18.0, (float) $data[0]['gst_rate']);
        $this->assertSame(20, $data[0]['current_stock_qty']);
        $this->assertStringContainsString('Brake Shoe', $data[0]['label']);

        // Match by part name too
        $this->actingAs($user)
            ->getJson(route('spare-parts.search', ['q' => 'brake']))
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_search_results_are_bounded(): void
    {
        $user = $this->makeUser();

        for ($i = 1; $i <= 15; $i++) {
            Customer::create([
                'first_name' => 'Customer'.$i,
                'mobile' => sprintf('9%09d', $i + 1),
                'customer_type' => 'regular',
            ]);
        }

        $this->actingAs($user)
            ->getJson(route('customers.search', ['q' => 'Customer']))
            ->assertOk()
            ->assertJsonCount(10);
    }

    public function test_customer_search_treats_like_wildcards_as_literals(): void
    {
        $user = $this->makeUser();

        Customer::create(['first_name' => 'Fifty', 'display_name' => 'Fifty Percent 50% Off', 'mobile' => '9000000010', 'customer_type' => 'regular']);
        Customer::create(['first_name' => 'FiftyX', 'display_name' => 'FiftyX 50A Off', 'mobile' => '9000000011', 'customer_type' => 'regular']);

        // "50%" must match only the literal percent sign, not "50A".
        $data = $this->actingAs($user)
            ->getJson(route('customers.search', ['q' => '50%']))
            ->assertOk()
            ->assertJsonCount(1);

        $this->assertSame('9000000010', $data->json('0.mobile'));
    }

    public function test_spare_part_search_treats_like_wildcards_as_literals(): void
    {
        $user = $this->makeUser();

        SparePart::create([
            'part_name' => 'Oil 50% Discount',
            'part_code' => 'OIL-50%',
            'category' => 'oil',
            'unit' => 'ltr',
            'purchase_price' => 30,
            'mrp' => 50,
            'labor_charge' => 0,
            'current_stock_qty' => 10,
            'reorder_level' => 5,
        ]);
        SparePart::create([
            'part_name' => 'Oil 5XA Variant',
            'part_code' => 'OIL-5XA',
            'category' => 'oil',
            'unit' => 'ltr',
            'purchase_price' => 30,
            'mrp' => 55,
            'labor_charge' => 0,
            'current_stock_qty' => 10,
            'reorder_level' => 5,
        ]);

        $data = $this->actingAs($user)
            ->getJson(route('spare-parts.search', ['q' => 'OIL-50%']))
            ->assertOk()
            ->assertJsonCount(1);

        $this->assertSame('OIL-50%', $data->json('0.part_code'));
    }

    public function test_vehicle_create_page_renders_with_customer_combobox(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('vehicles.create'))
            ->assertOk()
            ->assertSee('searchableCombobox');
    }
}
