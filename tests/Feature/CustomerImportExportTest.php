<?php

namespace Tests\Feature;

use App\ImportExport\AbstractImportDefinition;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private const EXPORT_HEADERS = [
        'external_ref', 'mobile', 'alt_mobile', 'email', 'first_name', 'last_name',
        'surname', 'company_name', 'display_name', 'address', 'city', 'gstin',
        'customer_type', 'created_at', 'updated_at',
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

    private function upload(string $content, string $mode = 'create_or_update'): string
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent('customers.csv', $content);

        $response = $this->actingAs($this->user)
            ->post(route('import-export.preview'), [
                'entity' => 'customers',
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

    // ------------------------------------------------------------- auth

    public function test_import_export_routes_require_authentication()
    {
        $this->get(route('import-export.index'))->assertRedirect(route('login'));
        $this->get(route('import-export.export', ['entity' => 'customers', 'format' => 'csv']))->assertRedirect(route('login'));
    }

    public function test_customer_list_has_import_and_export_actions()
    {
        $this->actingAs($this->user)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Import')
            ->assertSee('Export');
    }

    public function test_hub_lists_registered_entities()
    {
        $this->actingAs($this->user)
            ->get(route('import-export.index'))
            ->assertOk()
            ->assertSee('Customers')
            ->assertSee('Vehicle Brands');
    }

    // ------------------------------------------------------------- export

    public function test_export_empty_dataset_returns_only_headers()
    {
        $response = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'customers', 'format' => 'csv']));

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());

        $this->assertSame(self::EXPORT_HEADERS, $rows[0]);
        $this->assertCount(1, $rows);
    }

    public function test_export_contains_one_customer_with_stable_identifier()
    {
        Customer::create($this->customerFields(['external_ref' => 'EXT-77']));

        $response = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'customers', 'format' => 'csv']));

        $rows = $this->parseCsv($response->streamedContent());
        $this->assertCount(2, $rows);

        $idx = array_flip(self::EXPORT_HEADERS);
        $this->assertSame('EXT-77', $rows[1][$idx['external_ref']]);
        $this->assertSame('9876543210', $rows[1][$idx['mobile']]);
        $this->assertSame('regular', $rows[1][$idx['customer_type']]);
        $this->assertSame('Arjun', $rows[1][$idx['first_name']]);
        $this->assertNotEmpty($rows[1][$idx['created_at']]);
        $this->assertNotEmpty($rows[1][$idx['updated_at']]);
    }

    public function test_export_contains_many_customers()
    {
        Customer::create($this->customerFields(['mobile' => '9876543210']));
        Customer::create($this->customerFields(['mobile' => '9876543211', 'first_name' => 'Meera']));
        Customer::create($this->customerFields(['mobile' => '9876543212', 'first_name' => 'Ravi']));

        $response = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'customers', 'format' => 'csv']));

        $rows = $this->parseCsv($response->streamedContent());
        $this->assertCount(4, $rows);
    }

    public function test_export_sanitizes_formula_cells()
    {
        Customer::create($this->customerFields(['first_name' => '=1+1', 'last_name' => '', 'surname' => '']));

        $response = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'customers', 'format' => 'csv']));

        $content = $response->streamedContent();
        $this->assertStringContainsString("'=1+1", $content);
    }

    public function test_excel_export_is_a_documented_stub()
    {
        $response = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'customers', 'format' => 'xlsx']));

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertStringContainsString('Excel', session('error'));
    }

    // ------------------------------------------------------------- template

    public function test_template_uses_exact_import_headers_and_has_sample_rows()
    {
        $response = $this->actingAs($this->user)
            ->get(route('import-export.template', ['entity' => 'customers', 'format' => 'csv']));

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());

        $this->assertSame(self::EXPORT_HEADERS, $rows[0]);
        $this->assertGreaterThan(1, count($rows));
    }

    public function test_template_can_be_imported()
    {
        $template = $this->actingAs($this->user)
            ->get(route('import-export.template', ['entity' => 'customers', 'format' => 'csv']));

        $token = $this->upload($template->streamedContent(), 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $this->assertSame(2, Customer::count());
        $this->assertDatabaseHas('customers', ['external_ref' => 'CUS-0001', 'mobile' => '9876543210']);
        $this->assertDatabaseHas('customers', ['external_ref' => 'CUS-0002', 'mobile' => '9876543211']);
    }

    public function test_excel_template_is_a_documented_stub()
    {
        $response = $this->actingAs($this->user)
            ->get(route('import-export.template', ['entity' => 'customers', 'format' => 'xlsx']));

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertStringContainsString('Excel', session('error'));
    }

    // ------------------------------------------------------------- import flow

    public function test_full_import_flow_creates_customer()
    {
        $content = $this->csv([[
            'mobile' => '9876543210',
            'first_name' => 'Arjun',
            'last_name' => 'Patel',
            'email' => 'arjun@example.com',
            'customer_type' => 'regular',
            'external_ref' => 'EXT-100',
        ]]);

        $token = $this->upload($content, 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $this->assertDatabaseHas('customers', [
            'external_ref' => 'EXT-100',
            'mobile' => '9876543210',
            'first_name' => 'Arjun',
            'email' => 'arjun@example.com',
            'customer_type' => 'regular',
        ]);
    }

    public function test_preview_is_a_dry_run_and_creates_nothing()
    {
        $content = $this->csv([[
            'mobile' => '9876543210',
            'first_name' => 'Arjun',
            'customer_type' => 'regular',
        ]]);

        $token = $this->upload($content);

        $this->assertPreviewOk($token);
        $this->assertSame(0, Customer::count());
    }

    public function test_exported_file_can_be_reimported_without_duplicates()
    {
        Customer::create($this->customerFields([
            'external_ref' => 'EXT-1',
            'first_name' => 'Ravi',
        ]));

        $export = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'customers', 'format' => 'csv']));

        $token = $this->upload($export->streamedContent(), 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $this->assertSame(1, Customer::count());
        $this->assertDatabaseHas('customers', ['external_ref' => 'EXT-1', 'first_name' => 'Ravi']);
    }

    public function test_modified_export_updates_existing_customer_and_preserves_timestamps()
    {
        Customer::create($this->customerFields(['external_ref' => 'EXT-1', 'first_name' => 'Old']));

        $content = $this->csv([[
            'external_ref' => 'EXT-1',
            'mobile' => '9876543210',
            'first_name' => 'New',
            'customer_type' => 'regular',
            'created_at' => '2026-01-01 10:00:00',
            'updated_at' => '2026-01-02 10:00:00',
        ]]);

        $token = $this->upload($content, 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $this->assertSame(1, Customer::count());
        $this->assertDatabaseHas('customers', ['external_ref' => 'EXT-1', 'first_name' => 'New']);

        $customer = Customer::first();
        $this->assertSame('2026-01-02 10:00:00', $customer->updated_at->format('Y-m-d H:i:s'));
    }

    public function test_headers_are_matched_case_insensitively()
    {
        $content = "MOBILE,First_Name,Customer_Type\n9876543210,Arjun,regular\n";

        $token = $this->upload($content, 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $this->assertDatabaseHas('customers', ['mobile' => '9876543210', 'first_name' => 'Arjun']);
    }

    // ------------------------------------------------------------- duplicate handling

    public function test_duplicate_rows_inside_file_are_rejected()
    {
        $content = $this->csv([
            ['mobile' => '9876543210', 'first_name' => 'A', 'customer_type' => 'regular'],
            ['mobile' => '9876543210', 'first_name' => 'B', 'customer_type' => 'regular'],
        ]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('Duplicate value');

        $this->confirm($token)->assertOk();

        $this->assertSame(1, Customer::count());
    }

    public function test_ambiguous_match_is_reported_and_nothing_is_written()
    {
        Customer::create($this->customerFields(['external_ref' => 'EXT-A', 'mobile' => '9876543210', 'first_name' => 'A']));
        Customer::create($this->customerFields(['external_ref' => 'EXT-B', 'mobile' => '9876543211', 'first_name' => 'B']));

        // external_ref points at A, mobile points at B → two candidates.
        $content = $this->csv([[
            'external_ref' => 'EXT-A',
            'mobile' => '9876543211',
            'first_name' => 'X',
            'customer_type' => 'regular',
        ]]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('Matches more than one existing record');

        $this->confirm($token)->assertOk();

        $this->assertSame(2, Customer::count());
    }

    // ------------------------------------------------------------- validation

    public function test_invalid_mobile_is_rejected()
    {
        $content = $this->csv([[
            'mobile' => '123',
            'first_name' => 'Arjun',
            'customer_type' => 'regular',
        ]]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('format is invalid');

        $this->confirm($token)->assertOk();
        $this->assertSame(0, Customer::count());
    }

    public function test_invalid_email_is_rejected()
    {
        $content = $this->csv([[
            'mobile' => '9876543210',
            'first_name' => 'Arjun',
            'customer_type' => 'regular',
            'email' => 'not-an-email',
        ]]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('valid email');

        $this->confirm($token)->assertOk();
        $this->assertSame(0, Customer::count());
    }

    public function test_invalid_datetime_is_rejected()
    {
        $content = $this->csv([[
            'mobile' => '9876543210',
            'first_name' => 'Arjun',
            'customer_type' => 'regular',
            'created_at' => 'not-a-date',
        ]]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('valid date-time');

        $this->confirm($token)->assertOk();
        $this->assertSame(0, Customer::count());
    }

    public function test_missing_required_column_is_a_fatal_structure_error()
    {
        // No mobile column at all.
        $content = $this->csv([['first_name' => 'Arjun']]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('Missing required column');

        $this->confirm($token)->assertOk();
        $this->assertSame(0, Customer::count());
    }

    public function test_unknown_customer_identifier_creates_a_new_customer()
    {
        $content = $this->csv([[
            'external_ref' => 'EXT-NEW',
            'mobile' => '9876543210',
            'first_name' => 'Arjun',
            'customer_type' => 'regular',
        ]]);

        $token = $this->upload($content, 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $this->assertDatabaseHas('customers', ['external_ref' => 'EXT-NEW']);
    }

    // ------------------------------------------------------------- modes

    public function test_create_only_mode_skips_existing_customers()
    {
        Customer::create($this->customerFields(['mobile' => '9876543210', 'first_name' => 'Old']));

        $content = $this->csv([[
            'mobile' => '9876543210',
            'first_name' => 'New',
            'customer_type' => 'regular',
        ]]);

        $token = $this->upload($content, 'create_only');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('Create only');

        $this->confirm($token)->assertOk();

        $this->assertSame(1, Customer::count());
        $this->assertDatabaseHas('customers', ['mobile' => '9876543210', 'first_name' => 'Old']);
    }

    public function test_create_or_update_mode_updates_existing_customer_by_mobile()
    {
        Customer::create($this->customerFields(['mobile' => '9876543210', 'first_name' => 'Old']));

        $content = $this->csv([[
            'mobile' => '9876543210',
            'first_name' => 'New',
            'customer_type' => 'regular',
        ]]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('To update');

        $this->confirm($token)->assertOk();

        $this->assertSame(1, Customer::count());
        $this->assertDatabaseHas('customers', ['mobile' => '9876543210', 'first_name' => 'New']);
    }

    // ------------------------------------------------------------- safety

    public function test_critical_database_error_rolls_back_entire_import()
    {
        $definition = new class extends AbstractImportDefinition
        {
            private int $calls = 0;

            public function key(): string
            {
                return 'customers';
            }

            public function label(): string
            {
                return 'Customers';
            }

            public function applyCreate(array $data): object
            {
                $this->calls++;
                if ($this->calls === 2) {
                    throw new \RuntimeException('boom');
                }

                return Customer::create($data);
            }

            public function exportColumns(): array
            {
                return [];
            }

            public function exportQuery(array $filters = [])
            {
                return Customer::query();
            }

            public function exportFileName(): string
            {
                return 'customers';
            }
        };

        config(['import-export.entities.customers' => $definition::class]);

        $content = $this->csv([
            ['mobile' => '9876543210', 'first_name' => 'A', 'customer_type' => 'regular'],
            ['mobile' => '9876543211', 'first_name' => 'B', 'customer_type' => 'regular'],
        ]);

        $token = $this->upload($content, 'create_or_update');

        $this->assertPreviewOk($token);

        $this->actingAs($this->user)
            ->post(route('import-export.confirm', $token))
            ->assertOk()
            ->assertSee('rolled back');

        $this->assertSame(0, Customer::count());
    }

    public function test_confirm_cannot_be_replayed_after_success()
    {
        Customer::create($this->customerFields(['mobile' => '9876543210', 'first_name' => 'A']));

        $content = $this->csv([[
            'mobile' => '9876543210',
            'first_name' => 'B',
            'customer_type' => 'regular',
        ]]);

        $token = $this->upload($content, 'create_only');

        $this->confirm($token)->assertOk();

        $this->actingAs($this->user)
            ->post(route('import-export.confirm', $token))
            ->assertRedirect(route('import-export.index'))
            ->assertSessionHas('error');
    }

    // ------------------------------------------------------------- xlsx stub

    public function test_excel_import_is_a_documented_stub()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent('customers.xlsx', 'not-a-real-xlsx');

        $response = $this->actingAs($this->user)
            ->post(route('import-export.preview'), [
                'entity' => 'customers',
                'mode' => 'create_or_update',
                'file' => $file,
            ]);

        $response->assertRedirect();
        $token = Str::afterLast((string) $response->headers->get('Location'), '/');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertRedirect(route('import-export.index'))
            ->assertSessionHas('error');

        $this->assertStringContainsString('Excel', session('error'));
    }
}