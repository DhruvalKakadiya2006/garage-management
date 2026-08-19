<?php

namespace Tests\Feature;

use App\Models\CounterInvoice;
use App\Models\Customer;
use App\Models\JobCard;
use App\Models\LabourMaster;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class MasterDeletionRestrictTest extends TestCase
{
    use RefreshDatabase;
    use SeedsVehicleCatalog;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeCustomer(string $name = 'Dhruval', string $mobile = '9000000001'): Customer
    {
        return Customer::create(['first_name' => $name, 'mobile' => $mobile, 'customer_type' => 'regular']);
    }

    private function makeVehicle(Customer $customer, string $regNo = 'GJ-01-AB-1234'): Vehicle
    {
        return $this->makeCatalogVehicle($customer, $regNo, 'Ola', 'S1 Pro');
    }

    private function makePart(string $code = 'BRK-01', string $name = 'Brake Shoe', int $stock = 10): SparePart
    {
        return SparePart::create([
            'part_name' => $name,
            'part_code' => $code,
            'category' => 'battery',
            'unit' => 'pcs',
            'purchase_price' => 30,
            'mrp' => 50,
            'labor_charge' => 10,
            'gst_rate' => 18,
            'current_stock_qty' => $stock,
            'reorder_level' => 5,
        ]);
    }

    private function makeLabour(string $service = 'General Service'): LabourMaster
    {
        return LabourMaster::create(['service_name' => $service, 'category' => 'general', 'standard_rate' => 200, 'taxable' => true]);
    }

    private function makeJobCard(Customer $customer, Vehicle $vehicle): JobCard
    {
        return JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
        ]);
    }

    private function makeCounterInvoice(Customer $customer): CounterInvoice
    {
        return CounterInvoice::create([
            'invoice_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'gst_applicable' => false,
        ]);
    }

    public function test_deleting_customer_with_linked_job_card_is_blocked()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle($customer);
        $this->makeJobCard($customer, $vehicle);

        $this->actingAs($user)
            ->delete(route('customers.destroy', $customer))
            ->assertSessionHas('error', 'Cannot delete — this customer has existing job cards or invoices linked to it.');

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('job_cards', ['customer_id' => $customer->id]);
    }

    public function test_deleting_customer_with_linked_counter_invoice_is_blocked()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $this->makeCounterInvoice($customer);

        $this->actingAs($user)
            ->delete(route('customers.destroy', $customer))
            ->assertSessionHas('error', 'Cannot delete — this customer has existing job cards or invoices linked to it.');

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('counter_invoices', ['customer_id' => $customer->id]);
    }

    public function test_deleting_vehicle_with_linked_job_card_is_blocked()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle($customer);
        $this->makeJobCard($customer, $vehicle);

        $this->actingAs($user)
            ->delete(route('vehicles.destroy', $vehicle))
            ->assertSessionHas('error', 'Cannot delete — this vehicle has existing job cards or invoices linked to it.');

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id]);
        $this->assertDatabaseHas('job_cards', ['vehicle_id' => $vehicle->id]);
    }

    public function test_deleting_spare_part_used_in_job_card_part_is_blocked()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle($customer);
        $part = $this->makePart();
        $jobCard = $this->makeJobCard($customer, $vehicle);
        $jobCard->parts()->create([
            'spare_part_id' => $part->id,
            'quantity' => 1,
            'mrp' => 50,
            'labor_charge' => 10,
            'under_warranty' => false,
            'line_total' => 60,
        ]);

        $this->actingAs($user)
            ->delete(route('spare-parts.destroy', $part))
            ->assertSessionHas('error', 'Cannot delete — this part has existing job cards or invoices linked to it.');

        $this->assertDatabaseHas('spare_parts', ['id' => $part->id]);
        $this->assertDatabaseHas('job_card_parts', ['spare_part_id' => $part->id]);
    }

    public function test_deleting_spare_part_used_in_counter_invoice_item_is_blocked()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $part = $this->makePart();
        $invoice = $this->makeCounterInvoice($customer);
        $invoice->items()->create([
            'spare_part_id' => $part->id,
            'quantity' => 1,
            'mrp' => 50,
            'discount_percent' => 0,
            'line_total' => 50,
        ]);

        $this->actingAs($user)
            ->delete(route('spare-parts.destroy', $part))
            ->assertSessionHas('error', 'Cannot delete — this part has existing job cards or invoices linked to it.');

        $this->assertDatabaseHas('spare_parts', ['id' => $part->id]);
        $this->assertDatabaseHas('counter_invoice_items', ['spare_part_id' => $part->id]);
    }

    public function test_deleting_labour_master_used_in_job_card_labour_is_blocked()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle($customer);
        $labour = $this->makeLabour();
        $jobCard = $this->makeJobCard($customer, $vehicle);
        $jobCard->labours()->create([
            'labour_master_id' => $labour->id,
            'rate' => 200,
            'qty' => 1,
            'line_total' => 200,
        ]);

        $this->actingAs($user)
            ->delete(route('labour-masters.destroy', $labour))
            ->assertSessionHas('error', 'Cannot delete — this labour service has existing job cards or invoices linked to it.');

        $this->assertDatabaseHas('labour_masters', ['id' => $labour->id]);
        $this->assertDatabaseHas('job_card_labours', ['labour_master_id' => $labour->id]);
    }

    public function test_deleting_unlinked_customer_still_works()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();

        $this->actingAs($user)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success', 'Customer deleted.');

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_deleting_unlinked_vehicle_still_works()
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle($customer);

        $this->actingAs($user)
            ->delete(route('vehicles.destroy', $vehicle))
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success', 'Vehicle deleted.');

        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
    }

    public function test_deleting_unlinked_spare_part_still_works()
    {
        $user = $this->makeUser();
        $part = $this->makePart();

        $this->actingAs($user)
            ->delete(route('spare-parts.destroy', $part))
            ->assertRedirect(route('inventory.index', ['tab' => 'parts']))
            ->assertSessionHas('success', 'Spare part deleted.');

        $this->assertDatabaseMissing('spare_parts', ['id' => $part->id]);
    }

    public function test_deleting_unlinked_labour_master_still_works()
    {
        $user = $this->makeUser();
        $labour = $this->makeLabour();

        $this->actingAs($user)
            ->delete(route('labour-masters.destroy', $labour))
            ->assertRedirect(route('inventory.index', ['tab' => 'labour']))
            ->assertSessionHas('success', 'Labour service deleted.');

        $this->assertDatabaseMissing('labour_masters', ['id' => $labour->id]);
    }

    public function test_job_card_complaints_has_no_complaint_master_id_column()
    {
        $this->assertFalse(Schema::hasColumn('job_card_complaints', 'complaint_master_id'));
    }
}
