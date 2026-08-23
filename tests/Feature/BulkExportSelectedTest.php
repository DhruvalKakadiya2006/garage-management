<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\JobCard;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class BulkExportSelectedTest extends TestCase
{
    use RefreshDatabase;
    use SeedsVehicleCatalog;

    // ------------------------------------------------------------- helpers

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeCustomer(string $name, string $mobile): Customer
    {
        return Customer::create([
            'first_name' => $name,
            'mobile' => $mobile,
            'customer_type' => 'regular',
        ]);
    }

    private function makeVehicle(Customer $customer): Vehicle
    {
        return $this->makeCatalogVehicle(
            $customer,
            'GJ-01-AB-'.random_int(1000, 9999),
            'Ola',
            'S1 Pro'
        );
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

    private function parseCsv(string $content): array
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $rows = [];
        foreach (explode("\n", $content) as $line) {
            if ($line === '') {
                continue;
            }
            $rows[] = str_getcsv($line);
        }

        return $rows;
    }

    // ------------------------------------------------------------- customers

    public function test_customers_bulk_export_streams_only_selected_rows(): void
    {
        $user = $this->makeUser();
        $selected = $this->makeCustomer('Selected', '9000000001');
        $this->makeCustomer('Other', '9000000002');

        $response = $this->actingAs($user)->post(route('customers.bulk-export'), [
            'ids' => [$selected->id],
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'customers-selected.csv',
            $response->headers->get('Content-Disposition')
        );

        $rows = $this->parseCsv($response->streamedContent());
        $this->assertSame([
            'external_ref', 'mobile', 'alt_mobile', 'email', 'first_name', 'last_name',
            'surname', 'company_name', 'display_name', 'address', 'city', 'gstin',
            'customer_type', 'created_at', 'updated_at',
        ], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame('9000000001', $rows[1][1]);
    }

    public function test_bulk_export_requires_authentication(): void
    {
        $response = $this->postJson(route('customers.bulk-export'), ['ids' => [1]]);

        $response->assertRedirect(route('login'));
    }

    public function test_bulk_export_rejects_empty_or_malformed_ids(): void
    {
        $user = $this->makeUser();

        $empty = $this->actingAs($user)->postJson(route('customers.bulk-export'), ['ids' => []]);
        $empty->assertStatus(422)->assertJsonValidationErrors('ids');

        $malformed = $this->actingAs($user)->postJson(route('customers.bulk-export'), ['ids' => ['abc']]);
        $malformed->assertStatus(422)->assertJsonValidationErrors('ids.0');
    }

    // ------------------------------------------------------------- payments

    public function test_payments_bulk_export_includes_document_and_customer_columns(): void
    {
        $user = $this->makeUser();
        $customer = $this->makeCustomer('Arjun', '9000000010');
        $jobCard = $this->makeJobCard($customer, $this->makeVehicle($customer));

        $payment = Payment::create([
            'payable_id' => $jobCard->id,
            'payable_type' => JobCard::class,
            'amount' => 1500.5,
            'payment_mode' => 'cash',
            'payment_date' => '2026-08-10',
            'reference_no' => 'REF-001',
        ]);

        $response = $this->actingAs($user)->post(route('payments.bulk-export'), [
            'ids' => [$payment->id],
        ]);

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());
        $this->assertSame([
            'payment_date', 'payment_mode', 'document_type', 'document_no', 'customer',
            'amount', 'reference_no', 'notes', 'created_at', 'updated_at',
        ], $rows[0]);
        $this->assertSame('job_card', $rows[1][2]);
        $this->assertSame($jobCard->job_card_no, $rows[1][3]);
        $this->assertSame('Arjun', $rows[1][4]);
        $this->assertSame('1500.50', $rows[1][5]);
    }

    public function test_payments_bulk_export_ignores_unselected_payments(): void
    {
        $user = $this->makeUser();

        $customer = $this->makeCustomer('Meera', '9000000011');
        $jobCardA = $this->makeJobCard($customer, $this->makeVehicle($customer));
        $jobCardB = $this->makeJobCard($customer, $this->makeVehicle($customer));

        $paymentA = Payment::create([
            'payable_id' => $jobCardA->id,
            'payable_type' => JobCard::class,
            'amount' => 100,
            'payment_mode' => 'cash',
            'payment_date' => '2026-08-01',
        ]);
        Payment::create([
            'payable_id' => $jobCardB->id,
            'payable_type' => JobCard::class,
            'amount' => 200,
            'payment_mode' => 'upi',
            'payment_date' => '2026-08-02',
        ]);

        $response = $this->actingAs($user)->post(route('payments.bulk-export'), [
            'ids' => [$paymentA->id],
        ]);

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());
        $this->assertCount(2, $rows);
        $this->assertSame('100.00', $rows[1][5]);
    }

    // ------------------------------------------------------------- vehicle models

    public function test_vehicle_models_bulk_export_includes_brand(): void
    {
        $user = $this->makeUser();
        $model = $this->makeCatalogModel('Ola', 'S1 Pro');

        $response = $this->actingAs($user)->post(route('vehicle-models.bulk-export'), [
            'ids' => [$model->id],
        ]);

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());
        $this->assertSame([
            'external_ref', 'brand', 'name', 'battery_type', 'created_at', 'updated_at',
        ], $rows[0]);
        $this->assertSame('Ola', $rows[1][1]);
        $this->assertSame('S1 Pro', $rows[1][2]);
    }

    // ------------------------------------------------------------- job cards

    public function test_job_cards_bulk_export_streams_selected_cards(): void
    {
        $user = $this->makeUser();

        $customer = $this->makeCustomer('Ravi', '9000000012');
        $cardA = $this->makeJobCard($customer, $this->makeVehicle($customer));
        $cardB = $this->makeJobCard($customer, $this->makeVehicle($customer));

        $response = $this->actingAs($user)->post(route('job-cards.bulk-export'), [
            'ids' => [$cardA->id],
        ]);

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());
        $this->assertCount(2, $rows);
        $this->assertSame('job_card_no', $rows[0][1]);
        $this->assertSame($cardA->job_card_no, $rows[1][1]);
        $this->assertNotSame($cardB->job_card_no, $rows[1][1]);
    }

    // ------------------------------------------------------------- page wiring

    public function test_all_module_pages_render_the_export_selected_action(): void
    {
        $user = $this->makeUser();

        $pages = [
            ['route' => 'customers.index', 'endpoint' => 'customers.bulk-export'],
            ['route' => 'job-cards.index', 'endpoint' => 'job-cards.bulk-export'],
            ['route' => 'counter-invoices.index', 'endpoint' => 'counter-invoices.bulk-export'],
            ['route' => 'payments.index', 'endpoint' => 'payments.bulk-export'],
            ['route' => 'employees.index', 'endpoint' => 'employees.bulk-export'],
            ['route' => 'inventory.index', 'endpoint' => 'spare-parts.bulk-export'],
            ['route' => 'inventory.index', 'endpoint' => 'labour-masters.bulk-export'],
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($user)->get(route($page['route']));

            $response->assertOk();
            $response->assertSee('Export selected', false);
            $response->assertSee(route($page['endpoint']), false);
        }
    }

    public function test_vehicle_catalog_page_wires_export_for_both_scopes(): void
    {
        $user = $this->makeUser();
        $this->makeCatalogModel('Ola', 'S1 Pro');

        $response = $this->actingAs($user)->get(route('vehicle-catalog.index'));

        $response->assertOk();
        $response->assertSee('Export selected', false);
        $response->assertSee(route('vehicle-brands.bulk-export'), false);
        $response->assertSee(route('vehicle-models.bulk-export'), false);
    }
}
