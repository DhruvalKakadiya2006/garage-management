<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\JobCard;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class JobCardImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private const EXPORT_HEADERS = [
        'external_ref', 'job_card_no', 'job_date', 'customer_external_id', 'customer_mobile',
        'vehicle_external_id', 'vehicle_registration_no', 'technician_external_id', 'supervisor_external_id',
        'odometer_reading_km', 'job_type', 'promised_delivery_date', 'promised_delivery_time', 'status',
        'discount_percent', 'discount_amount', 'gst_applicable', 'cgst_amount', 'sgst_amount',
        'round_off_amount', 'grand_total', 'remarks', 'invoice_generated_at', 'estimate_generated_at',
        'created_at', 'updated_at',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ------------------------------------------------------------- helpers

    private function customerFields(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Arjun',
            'last_name' => 'Patel',
            'mobile' => '9876543210',
            'customer_type' => 'regular',
            'external_ref' => null,
        ], $overrides);
    }

    private function makeCustomer(array $overrides = []): Customer
    {
        return Customer::create($this->customerFields($overrides));
    }

    private function makeVehicle(array $overrides = []): Vehicle
    {
        $customerId = $overrides['customer_id'] ?? $this->makeCustomer()->id;

        return Vehicle::create(array_merge([
            'customer_id' => $customerId,
            'registration_no' => 'GJ-05-AB-'.str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
            'external_ref' => null,
        ], $overrides));
    }

    private function makeEmployee(array $overrides = []): Employee
    {
        return Employee::create(array_merge([
            'name' => 'Ramesh Technician',
            'role' => 'technician',
            'mobile' => '9123456789',
            'active' => true,
            'external_ref' => null,
        ], $overrides));
    }

    private function makeJobCard(array $overrides = []): JobCard
    {
        $customer = Customer::find($overrides['customer_id'] ?? null)
            ?? Customer::where('external_ref', 'CUS-0001')->first()
            ?? $this->makeCustomer();
        $vehicle = Vehicle::find($overrides['vehicle_id'] ?? null)
            ?? Vehicle::where('external_ref', 'VEH-0001')->first()
            ?? $this->makeVehicle(['customer_id' => $customer->id]);
        $technician = $overrides['assigned_technician_id'] ?? $this->makeEmployee();
        $technicianId = $technician instanceof Employee ? $technician->id : $technician;

        return JobCard::create(array_merge([
            'job_date' => '2026-01-15',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'assigned_technician_id' => $technicianId,
            'external_ref' => null,
        ], $overrides));
    }

    private function jobCardRow(array $overrides = []): array
    {
        return array_merge([
            'external_ref' => 'JC-EXP-0001',
            'job_card_no' => '',
            'job_date' => '2026-01-15',
            'customer_external_id' => 'CUS-0001',
            'customer_mobile' => '',
            'vehicle_external_id' => 'VEH-0001',
            'vehicle_registration_no' => '',
            'technician_external_id' => 'TECH-0001',
            'supervisor_external_id' => '',
            'odometer_reading_km' => '12500',
            'job_type' => 'paid_service',
            'promised_delivery_date' => '2026-01-16',
            'promised_delivery_time' => '18:00',
            'status' => 'received',
            'discount_percent' => '0',
            'discount_amount' => '0',
            'gst_applicable' => 'false',
            'cgst_amount' => '0',
            'sgst_amount' => '0',
            'round_off_amount' => '0',
            'grand_total' => '',
            'remarks' => 'Brake pad replacement and general service.',
            'invoice_generated_at' => '',
            'estimate_generated_at' => '',
            'created_at' => '2026-01-15 09:30:00',
            'updated_at' => '2026-01-15 09:30:00',
        ], $overrides);
    }

    /**
     * Create the reference records the job card sample data depends on.
     */
    private function seedReferences(): void
    {
        $customer = $this->makeCustomer(['external_ref' => 'CUS-0001', 'mobile' => '9876543210']);
        $this->makeVehicle(['customer_id' => $customer->id, 'external_ref' => 'VEH-0001', 'registration_no' => 'GJ-05-AB-1234']);
        $this->makeEmployee(['external_ref' => 'TECH-0001']);
        $this->makeEmployee(['external_ref' => 'TECH-0002']);
    }

    private function csv(array $rows, ?array $headers = null): string
    {
        $headers ??= array_keys($rows[0]);

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            $values = [];
            foreach ($headers as $header) {
                $values[] = $row[$header] ?? '';
            }
            fputcsv($handle, $values);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
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

    private function upload(string $content, string $mode = 'create_or_update', string $entity = 'job_cards'): string
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent('job-cards.csv', $content);

        $response = $this->actingAs($this->user)
            ->post(route('import-export.preview'), [
                'entity' => $entity,
                'mode' => $mode,
                'file' => $file,
            ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);

        return Str::afterLast($location, '/');
    }

    private function assertPreviewOk(string $token): void
    {
        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk();
    }

    private function confirm(string $token)
    {
        return $this->actingAs($this->user)->post(route('import-export.confirm', $token));
    }

    // ------------------------------------------------------------- UI

    public function test_job_card_list_has_import_and_export_actions()
    {
        $this->actingAs($this->user)
            ->get(route('job-cards.index'))
            ->assertOk()
            ->assertSee('Import')
            ->assertSee('Export');
    }

    public function test_hub_lists_job_cards_entity()
    {
        $this->actingAs($this->user)
            ->get(route('import-export.index'))
            ->assertOk()
            ->assertSee('Job Cards');
    }

    // ------------------------------------------------------------- export

    public function test_export_empty_dataset_returns_only_headers()
    {
        $response = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'job_cards', 'format' => 'csv']));

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());

        $this->assertSame(self::EXPORT_HEADERS, $rows[0]);
        $this->assertCount(1, $rows);
    }

    public function test_export_contains_job_card_with_resolved_references_and_historical_values()
    {
        $customer = $this->makeCustomer(['external_ref' => 'CUS-0001']);
        $vehicle = $this->makeVehicle(['customer_id' => $customer->id, 'external_ref' => 'VEH-0001', 'registration_no' => 'GJ-05-AB-1234']);
        $technician = $this->makeEmployee(['external_ref' => 'TECH-0001']);

        $jobCard = $this->makeJobCard([
            'external_ref' => 'JC-EXP-0001',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'assigned_technician_id' => $technician->id,
            'status' => 'delivered',
            'grand_total' => 5000,
            'job_card_no' => 'JC-0001',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'job_cards', 'format' => 'csv']));

        $rows = $this->parseCsv($response->streamedContent());
        $this->assertCount(2, $rows);

        $idx = array_flip(self::EXPORT_HEADERS);
        $row = $rows[1];

        $this->assertSame('JC-EXP-0001', $row[$idx['external_ref']]);
        $this->assertSame('JC-0001', $row[$idx['job_card_no']]);
        $this->assertSame('2026-01-15', $row[$idx['job_date']]);
        $this->assertSame('CUS-0001', $row[$idx['customer_external_id']]);
        $this->assertSame('9876543210', $row[$idx['customer_mobile']]);
        $this->assertSame('VEH-0001', $row[$idx['vehicle_external_id']]);
        $this->assertSame('GJ-05-AB-1234', $row[$idx['vehicle_registration_no']]);
        $this->assertSame('TECH-0001', $row[$idx['technician_external_id']]);
        $this->assertSame('delivered', $row[$idx['status']]);
        $this->assertSame('5000.00', $row[$idx['grand_total']]);
        $this->assertNotEmpty($row[$idx['created_at']]);
    }

    public function test_export_can_be_filtered_by_status()
    {
        $customer = $this->makeCustomer();
        $vehicle = $this->makeVehicle(['customer_id' => $customer->id]);

        $this->makeJobCard(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'status' => 'delivered', 'external_ref' => 'JC-A']);
        $this->makeJobCard(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'status' => 'in_progress', 'external_ref' => 'JC-B']);

        $response = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'job_cards', 'format' => 'csv', 'status' => 'in_progress']));

        $rows = $this->parseCsv($response->streamedContent());
        $this->assertCount(2, $rows); // header + 1 filtered row

        $idx = array_flip(self::EXPORT_HEADERS);
        $this->assertSame('JC-B', $rows[1][$idx['external_ref']]);
    }

    public function test_excel_export_is_a_documented_stub()
    {
        $response = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'job_cards', 'format' => 'xlsx']));

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertStringContainsString('Excel', session('error'));
    }

    // ------------------------------------------------------------- template

    public function test_template_uses_exact_import_headers_and_has_sample_rows()
    {
        $response = $this->actingAs($this->user)
            ->get(route('import-export.template', ['entity' => 'job_cards', 'format' => 'csv']));

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());

        $this->assertSame(self::EXPORT_HEADERS, $rows[0]);
        $this->assertGreaterThan(1, count($rows));
    }

    public function test_template_can_be_imported_when_referenced_records_exist()
    {
        $this->seedReferences();

        $template = $this->actingAs($this->user)
            ->get(route('import-export.template', ['entity' => 'job_cards', 'format' => 'csv']));

        $token = $this->upload($template->streamedContent(), 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $this->assertSame(2, JobCard::count());
        $this->assertDatabaseHas('job_cards', ['external_ref' => 'JC-EXP-0001', 'status' => 'received']);
        $this->assertDatabaseHas('job_cards', ['external_ref' => 'JC-EXP-0002', 'status' => 'in_progress', 'grand_total' => 4500]);
    }

    // ------------------------------------------------------------- import flow

    public function test_full_import_creates_job_card_and_resolves_references()
    {
        $this->seedReferences();

        $content = $this->csv([$this->jobCardRow()]);

        $token = $this->upload($content, 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $customer = Customer::where('external_ref', 'CUS-0001')->firstOrFail();
        $vehicle = Vehicle::where('external_ref', 'VEH-0001')->firstOrFail();
        $technician = Employee::where('external_ref', 'TECH-0001')->firstOrFail();

        $this->assertDatabaseHas('job_cards', [
            'external_ref' => 'JC-EXP-0001',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'assigned_technician_id' => $technician->id,
            'status' => 'received',
        ]);
    }

    public function test_import_matches_customer_and_vehicle_by_business_keys()
    {
        $customer = $this->makeCustomer(['external_ref' => 'CUS-0001', 'mobile' => '9876543210']);
        $this->makeVehicle(['customer_id' => $customer->id, 'external_ref' => 'VEH-0001', 'registration_no' => 'GJ-05-AB-1234']);
        $this->makeEmployee(['external_ref' => 'TECH-0001']);

        $content = $this->csv([$this->jobCardRow([
            'customer_external_id' => '',
            'customer_mobile' => '9876543210',
            'vehicle_external_id' => '',
            'vehicle_registration_no' => 'GJ-05-AB-1234',
        ])]);

        $token = $this->upload($content, 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $this->assertDatabaseHas('job_cards', [
            'external_ref' => 'JC-EXP-0001',
            'customer_id' => $customer->id,
        ]);
    }

    public function test_preview_is_a_dry_run_and_creates_nothing()
    {
        $this->seedReferences();

        $content = $this->csv([$this->jobCardRow()]);

        $token = $this->upload($content);

        $this->assertPreviewOk($token);
        $this->assertSame(0, JobCard::count());
    }

    public function test_exported_file_can_be_reimported_without_duplicates()
    {
        $this->seedReferences();
        $this->makeJobCard(['external_ref' => 'JC-EXP-0001', 'status' => 'received']);

        $export = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'job_cards', 'format' => 'csv']));

        $token = $this->upload($export->streamedContent(), 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $this->assertSame(1, JobCard::count());
    }

    public function test_modified_export_updates_existing_job_card_without_recalculating()
    {
        $this->seedReferences();
        $this->makeJobCard(['external_ref' => 'JC-EXP-0001', 'status' => 'received', 'grand_total' => 5000]);

        $content = $this->csv([$this->jobCardRow([
            'job_card_no' => 'JC-0001',
            'status' => 'delivered',
            'grand_total' => '5000',
        ])]);

        $token = $this->upload($content, 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $this->assertSame(1, JobCard::count());
        $this->assertDatabaseHas('job_cards', ['external_ref' => 'JC-EXP-0001', 'status' => 'delivered', 'grand_total' => 5000]);
    }

    public function test_blank_grand_total_keeps_the_card_unbilled()
    {
        $this->seedReferences();

        $content = $this->csv([$this->jobCardRow()]);

        $token = $this->upload($content, 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $jobCard = JobCard::where('external_ref', 'JC-EXP-0001')->firstOrFail();
        $this->assertNull($jobCard->grand_total);
        $this->assertFalse($jobCard->is_billed);
    }

    public function test_historical_timestamps_are_preserved_on_import()
    {
        $this->seedReferences();

        $content = $this->csv([$this->jobCardRow()]);

        $token = $this->upload($content, 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $jobCard = JobCard::where('external_ref', 'JC-EXP-0001')->firstOrFail();
        $this->assertSame('2026-01-15 09:30:00', $jobCard->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-15 09:30:00', $jobCard->updated_at->format('Y-m-d H:i:s'));
    }

    // ------------------------------------------------------------- duplicate detection

    public function test_duplicate_job_card_numbers_inside_file_are_rejected()
    {
        $this->seedReferences();

        $content = $this->csv([
            $this->jobCardRow(['job_card_no' => 'JC-0001']),
            $this->jobCardRow(['external_ref' => 'JC-EXP-0003', 'job_card_no' => 'JC-0001']),
        ]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('Duplicate value');

        $this->confirm($token)->assertOk();

        $this->assertSame(1, JobCard::count());
    }

    // ------------------------------------------------------------- dependency resolution

    public function test_missing_customer_reference_is_an_error_and_row_is_skipped()
    {
        $this->seedReferences();

        $content = $this->csv([$this->jobCardRow(['customer_external_id' => 'CUS-9999', 'customer_mobile' => ''])]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('No matching Customers found');

        $this->confirm($token)->assertOk();
        $this->assertSame(0, JobCard::count());
    }

    public function test_missing_vehicle_reference_is_an_error_and_row_is_skipped()
    {
        $this->seedReferences();

        $content = $this->csv([$this->jobCardRow(['vehicle_external_id' => 'VEH-9999', 'vehicle_registration_no' => ''])]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('No matching Vehicles found');

        $this->confirm($token)->assertOk();
        $this->assertSame(0, JobCard::count());
    }

    public function test_vehicle_belonging_to_another_customer_is_rejected()
    {
        $customerA = $this->makeCustomer(['external_ref' => 'CUS-A', 'mobile' => '9876543210']);
        $this->makeVehicle(['customer_id' => $customerA->id, 'external_ref' => 'VEH-A']);
        $customerB = $this->makeCustomer(['external_ref' => 'CUS-B', 'mobile' => '9876543211']);
        $this->makeVehicle(['customer_id' => $customerB->id, 'external_ref' => 'VEH-B']);
        $this->makeEmployee(['external_ref' => 'TECH-0001']);

        $content = $this->csv([$this->jobCardRow([
            'external_ref' => 'JC-X',
            'customer_external_id' => 'CUS-A',
            'customer_mobile' => '',
            'vehicle_external_id' => 'VEH-B',
            'vehicle_registration_no' => '',
        ])]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('does not belong to the referenced customer');

        $this->confirm($token)->assertOk();
        $this->assertSame(0, JobCard::count());
    }

    public function test_ambiguous_customer_reference_is_reported_and_nothing_is_written()
    {
        $customerA = $this->makeCustomer(['external_ref' => 'CUS-A', 'mobile' => '9876543210']);
        $customerB = $this->makeCustomer(['external_ref' => 'CUS-B', 'mobile' => '9876543211']);
        $this->makeVehicle(['customer_id' => $customerA->id, 'external_ref' => 'VEH-A']);
        $this->makeEmployee(['external_ref' => 'TECH-0001']);

        // external_ref points at A, mobile points at B → two candidates.
        $content = $this->csv([$this->jobCardRow([
            'external_ref' => 'JC-X',
            'customer_external_id' => 'CUS-A',
            'customer_mobile' => '9876543211',
            'vehicle_external_id' => 'VEH-A',
            'vehicle_registration_no' => '',
        ])]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('Reference is ambiguous');

        $this->confirm($token)->assertOk();
        $this->assertSame(0, JobCard::count());
    }

    // ------------------------------------------------------------- validation

    public function test_invalid_job_date_is_rejected()
    {
        $this->seedReferences();

        $content = $this->csv([$this->jobCardRow(['job_date' => 'not-a-date'])]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('valid date');

        $this->confirm($token)->assertOk();
        $this->assertSame(0, JobCard::count());
    }

    public function test_negative_odometer_reading_is_rejected()
    {
        $this->seedReferences();

        $content = $this->csv([$this->jobCardRow(['odometer_reading_km' => '-5'])]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('at least 0');

        $this->confirm($token)->assertOk();
        $this->assertSame(0, JobCard::count());
    }

    public function test_invalid_status_is_rejected()
    {
        $this->seedReferences();

        $content = $this->csv([$this->jobCardRow(['status' => 'bogus'])]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('one of');

        $this->confirm($token)->assertOk();
        $this->assertSame(0, JobCard::count());
    }

    public function test_non_numeric_grand_total_is_rejected()
    {
        $this->seedReferences();

        $content = $this->csv([$this->jobCardRow(['grand_total' => 'abc'])]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('must be a number');

        $this->confirm($token)->assertOk();
        $this->assertSame(0, JobCard::count());
    }

    public function test_missing_required_column_is_a_fatal_structure_error()
    {
        $this->seedReferences();

        $fullRow = $this->jobCardRow();
        $headers = array_values(array_filter(array_keys($fullRow), fn ($header) => $header !== 'job_date'));

        $row = $fullRow;
        unset($row['job_date']);

        $content = $this->csv([$row], $headers);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('Missing required column');

        $this->confirm($token)->assertOk();
        $this->assertSame(0, JobCard::count());
    }

    // ------------------------------------------------------------- modes

    public function test_create_only_mode_skips_existing_job_cards()
    {
        $this->seedReferences();
        $this->makeJobCard(['external_ref' => 'JC-EXP-0001', 'status' => 'received']);

        $content = $this->csv([$this->jobCardRow(['job_card_no' => 'JC-0001', 'status' => 'delivered'])]);

        $token = $this->upload($content, 'create_only');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('Create only');

        $this->confirm($token)->assertOk();

        $this->assertSame(1, JobCard::count());
        $this->assertDatabaseHas('job_cards', ['external_ref' => 'JC-EXP-0001', 'status' => 'received']);
    }

    public function test_create_or_update_mode_updates_existing_job_card_by_job_card_no()
    {
        $this->seedReferences();
        $this->makeJobCard(['external_ref' => 'JC-OTHER', 'job_card_no' => 'JC-0001', 'status' => 'received']);

        $content = $this->csv([$this->jobCardRow(['external_ref' => '', 'job_card_no' => 'JC-0001', 'status' => 'delivered'])]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('Update');

        $this->confirm($token)->assertOk();

        $this->assertSame(1, JobCard::count());
        $this->assertDatabaseHas('job_cards', ['job_card_no' => 'JC-0001', 'status' => 'delivered']);
    }

    // ------------------------------------------------------------- round trip

    public function test_full_round_trip_into_a_clean_database()
    {
        // 1. Source records.
        $customer = $this->makeCustomer(['external_ref' => 'CUS-0001', 'mobile' => '9876543210']);
        $this->makeCustomer(['external_ref' => 'CUS-0002', 'mobile' => '9876543211', 'first_name' => 'Meera']);
        $this->makeVehicle(['customer_id' => $customer->id, 'external_ref' => 'VEH-0001', 'registration_no' => 'GJ-05-AB-1234']);
        $technician = $this->makeEmployee(['external_ref' => 'TECH-0001']);

        $this->makeJobCard([
            'external_ref' => 'JC-EXP-0001',
            'customer_id' => $customer->id,
            'vehicle_id' => Vehicle::where('external_ref', 'VEH-0001')->firstOrFail()->id,
            'assigned_technician_id' => $technician->id,
            'status' => 'delivered',
            'grand_total' => 5000,
            'job_card_no' => 'JC-0001',
        ]);

        $sourceCustomerCount = Customer::count();
        $sourceVehicleCount = Vehicle::count();
        $sourceJobCardCount = JobCard::count();

        // 2. Export both entities.
        $customersExport = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'customers', 'format' => 'csv']))
            ->streamedContent();

        $jobCardsExport = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'job_cards', 'format' => 'csv']))
            ->streamedContent();

        // 3. Wipe to a clean database (child tables first for FK safety).
        JobCard::query()->delete();
        Employee::query()->delete();
        Vehicle::query()->delete();
        Customer::query()->delete();

        // 4. Recreate the "ensure required vehicles exist" step of the guide:
        //    customers come from the import, vehicles/employees from the UI.
        $customerToken = $this->upload($customersExport, 'create_or_update', 'customers');
        $this->assertPreviewOk($customerToken);
        $this->confirm($customerToken)->assertOk();

        $restoredCustomer = Customer::where('external_ref', 'CUS-0001')->firstOrFail();
        $this->makeVehicle(['customer_id' => $restoredCustomer->id, 'external_ref' => 'VEH-0001', 'registration_no' => 'GJ-05-AB-1234']);
        $this->makeEmployee(['external_ref' => 'TECH-0001']);

        // 5. Import the job cards export.
        $jobCardToken = $this->upload($jobCardsExport, 'create_or_update', 'job_cards');
        $this->assertPreviewOk($jobCardToken);
        $this->confirm($jobCardToken)->assertOk();

        // 6. Verify counts, relationships and historical values.
        $this->assertSame($sourceCustomerCount, Customer::count());
        $this->assertSame($sourceVehicleCount, Vehicle::count());
        $this->assertSame($sourceJobCardCount, JobCard::count());

        $jobCard = JobCard::where('external_ref', 'JC-EXP-0001')->firstOrFail();
        $this->assertSame('CUS-0001', $jobCard->customer->external_ref);
        $this->assertSame('9876543210', $jobCard->customer->mobile);
        $this->assertSame('VEH-0001', $jobCard->vehicle->external_ref);
        $this->assertSame('GJ-05-AB-1234', $jobCard->vehicle->registration_no);
        $this->assertSame('TECH-0001', $jobCard->assignedTechnician->external_ref);
        $this->assertSame('delivered', $jobCard->status);
        $this->assertSame(5000.0, (float) $jobCard->grand_total);
        $this->assertSame('JC-0001', $jobCard->job_card_no);

        // Re-importing the same export again must not create duplicates.
        $again = $this->upload($jobCardsExport, 'create_or_update', 'job_cards');
        $this->assertPreviewOk($again);
        $this->confirm($again)->assertOk();
        $this->assertSame($sourceJobCardCount, JobCard::count());
    }
}