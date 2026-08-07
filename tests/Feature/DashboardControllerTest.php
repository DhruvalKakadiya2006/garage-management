<?php

namespace Tests\Feature;

use App\Models\CounterInvoice;
use App\Models\Customer;
use App\Models\JobCard;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_todays_delivered_job_card_counts_towards_month_revenue(): void
    {
        $customer = Customer::create(['first_name' => 'Test', 'mobile' => '1111111111']);
        $vehicle = Vehicle::create(['customer_id' => $customer->id, 'registration_no' => 'GJ01AB1234']);

        JobCard::create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_date' => Carbon::today(),
            'job_type' => 'paid_service',
            'status' => 'delivered',
            'grand_total' => 500,
        ]);

        $response = $this->get('/dashboard');

        $response
            ->assertOk()
            ->assertViewHas('monthRevenue');

        $this->assertEqualsWithDelta(500.0, (float) $response->viewData('monthRevenue'), 0.001);
    }

    public function test_todays_counter_invoice_counts_towards_month_revenue(): void
    {
        CounterInvoice::create([
            'invoice_date' => Carbon::today(),
            'grand_total' => 750,
        ]);

        $response = $this->get('/dashboard');

        $response
            ->assertOk()
            ->assertViewHas('monthRevenue');

        $this->assertEqualsWithDelta(750.0, (float) $response->viewData('monthRevenue'), 0.001);
    }

    public function test_todays_job_cards_are_counted_in_today_tiles(): void
    {
        $customer = Customer::create(['first_name' => 'Test', 'mobile' => '1111111111']);
        $vehicle = Vehicle::create(['customer_id' => $customer->id, 'registration_no' => 'GJ01AB1234']);

        JobCard::create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_date' => Carbon::today(),
            'job_type' => 'free_service',
            'status' => 'received',
            'grand_total' => 0,
        ]);

        $response = $this->get('/dashboard');

        $response
            ->assertOk()
            ->assertViewHas('todayJobCardsTotal');

        $this->assertEquals(1, $response->viewData('todayJobCardsTotal'));
    }

    public function test_job_cards_outside_this_month_are_excluded_from_month_revenue(): void
    {
        $customer = Customer::create(['first_name' => 'Test', 'mobile' => '1111111111']);
        $vehicle = Vehicle::create(['customer_id' => $customer->id, 'registration_no' => 'GJ01AB1234']);

        JobCard::create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_date' => Carbon::now()->subMonth()->startOfMonth(),
            'job_type' => 'paid_service',
            'status' => 'delivered',
            'grand_total' => 999,
        ]);

        $response = $this->get('/dashboard');

        $response->assertOk();

        $this->assertEqualsWithDelta(0.0, (float) $response->viewData('monthRevenue'), 0.001);
    }
}
