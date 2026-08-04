<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstimateCreateRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimate_create_page_renders(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Test Customer',
            'mobile' => '9999999999',
            'customer_type' => 'walk-in',
        ]);
        Vehicle::create([
            'customer_id' => $customer->id,
            'registration_no' => 'GJ05TEST0001',
        ]);

        $response = $this->actingAs($user)->get(route('estimates.create'));

        $response->assertOk();
        $response->assertSee('Create New Estimate');
    }
}
