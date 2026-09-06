<?php

namespace Tests\Feature;

use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SparePartImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private const EXPORT_HEADERS = [
        'external_ref', 'part_name', 'part_code', 'category',
        'unit', 'purchase_price', 'mrp', 'labor_charge', 'hsn_code', 'gst_rate',
        'current_stock_qty', 'reorder_level', 'created_at', 'updated_at',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ------------------------------------------------------------- helpers

    private function partFields(array $overrides = []): array
    {
        return array_merge([
            'part_name' => 'Brake Shoe Set',
            'part_code' => 'BRK-001',
            'unit' => 'set',
            'purchase_price' => 350,
            'mrp' => 450,
            'labor_charge' => 80,
            'current_stock_qty' => 25,
            'reorder_level' => 10,
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

        $file = UploadedFile::fake()->createWithContent('spare-parts.csv', $content);

        $response = $this->actingAs($this->user)
            ->post(route('import-export.preview'), [
                'entity' => 'spare_parts',
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
        $this->get(route('import-export.export', ['entity' => 'spare_parts']))->assertRedirect(route('login'));
        $this->post(route('import-export.preview'))->assertRedirect(route('login'));
    }

    public function test_inventory_has_import_and_export_actions()
    {
        $this->actingAs($this->user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Import CSV')
            ->assertSee(route('import-export.preview'), false)
            ->assertSee('name="entity" value="spare_parts"', false)
            ->assertSee(route('import-export.export', ['entity' => 'spare_parts']))
            ->assertSee(route('import-export.template', ['entity' => 'spare_parts']));
    }

    // ------------------------------------------------------------- export

    public function test_export_empty_dataset_returns_only_headers()
    {
        $response = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'spare_parts']));

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());

        $this->assertSame(self::EXPORT_HEADERS, $rows[0]);
        $this->assertCount(1, $rows);
    }

    public function test_export_contains_one_spare_part_with_stable_identifier()
    {
        SparePart::create($this->partFields(['external_ref' => 'EXT-77']));

        $response = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'spare_parts']));

        $rows = $this->parseCsv($response->streamedContent());
        $this->assertCount(2, $rows);

        $idx = array_flip(self::EXPORT_HEADERS);
        $this->assertSame('EXT-77', $rows[1][$idx['external_ref']]);
        $this->assertSame('Brake Shoe Set', $rows[1][$idx['part_name']]);
        $this->assertSame('BRK-001', $rows[1][$idx['part_code']]);
        $this->assertSame('set', $rows[1][$idx['unit']]);
        $this->assertSame('350.00', $rows[1][$idx['purchase_price']]);
        $this->assertSame('450.00', $rows[1][$idx['mrp']]);
        $this->assertSame('25', $rows[1][$idx['current_stock_qty']]);
        $this->assertNotEmpty($rows[1][$idx['created_at']]);
    }

    public function test_export_contains_many_spare_parts()
    {
        SparePart::create($this->partFields(['part_code' => 'BRK-001']));
        SparePart::create($this->partFields(['part_code' => 'LMP-002', 'part_name' => 'Headlight Bulb']));
        SparePart::create($this->partFields(['part_code' => 'CHN-003', 'part_name' => 'Chain Kit']));

        $response = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'spare_parts']));

        $rows = $this->parseCsv($response->streamedContent());
        $this->assertCount(4, $rows);
    }

    /** @test */
    public function test_export_respects_low_stock_filter()
    {
        $this->markTestSkipped('Low stock filter removed - stock management not used');
    }

    public function test_export_sanitizes_formula_cells()
    {
        SparePart::create($this->partFields(['part_name' => '=1+1']));

        $response = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'spare_parts']));

        $content = $response->streamedContent();
        $this->assertStringContainsString("'=1+1", $content);
    }

    // ------------------------------------------------------------- template

    public function test_template_uses_exact_import_headers_and_has_sample_rows()
    {
        $response = $this->actingAs($this->user)
            ->get(route('import-export.template', ['entity' => 'spare_parts']));

        $response->assertOk();
        $rows = $this->parseCsv($response->streamedContent());

        $this->assertSame(self::EXPORT_HEADERS, $rows[0]);
        $this->assertGreaterThan(1, count($rows));
    }

    public function test_template_can_be_imported()
    {
        $template = $this->actingAs($this->user)
            ->get(route('import-export.template', ['entity' => 'spare_parts']));

        $token = $this->upload($template->streamedContent(), 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $this->assertSame(2, SparePart::count());
        $this->assertDatabaseHas('spare_parts', ['external_ref' => 'SP-0001', 'part_code' => 'BRK-001', 'part_name' => 'Brake Shoe Set']);
        $this->assertDatabaseHas('spare_parts', ['external_ref' => 'SP-0002', 'part_code' => 'LMP-002']);
    }

    // ------------------------------------------------------------- import flow

    public function test_full_import_flow_creates_spare_part()
    {
        $content = $this->csv([[
            'part_name' => 'Chain Kit',
            'part_code' => 'CHN-003',
            'category' => 'drive-system',
            'unit' => 'pcs',
            'purchase_price' => '250',
            'mrp' => '320',
            'labor_charge' => '60',
            'hsn_code' => '87141090',
            'gst_rate' => '18',
            'current_stock_qty' => '15',
            'reorder_level' => '5',
            'external_ref' => 'EXT-100',
        ]]);

        $token = $this->upload($content, 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $this->assertDatabaseHas('spare_parts', [
            'external_ref' => 'EXT-100',
            'part_name' => 'Chain Kit',
            'part_code' => 'CHN-003',
            'unit' => 'pcs',
            'purchase_price' => 250,
            'mrp' => 320,
            'current_stock_qty' => 15,
        ]);
    }

    public function test_preview_is_a_dry_run_and_creates_nothing()
    {
        $content = $this->csv([[
            'part_name' => 'Chain Kit',
            'part_code' => 'CHN-003',
            'category' => 'brakes',
            'unit' => 'pcs',
            'purchase_price' => '250',
            'mrp' => '320',
        ]]);

        $token = $this->upload($content);

        $this->assertPreviewOk($token);
        $this->assertSame(0, SparePart::count());
    }

    public function test_exported_file_can_be_reimported_without_duplicates()
    {
        SparePart::create($this->partFields(['external_ref' => 'EXT-1', 'part_name' => 'Brake Shoe']));

        $export = $this->actingAs($this->user)
            ->get(route('import-export.export', ['entity' => 'spare_parts']));

        $token = $this->upload($export->streamedContent(), 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $this->assertSame(1, SparePart::count());
        $this->assertDatabaseHas('spare_parts', ['external_ref' => 'EXT-1', 'part_name' => 'Brake Shoe']);
    }

    public function test_modified_export_updates_existing_part_and_preserves_timestamps()
    {
        SparePart::create($this->partFields(['external_ref' => 'EXT-1', 'part_name' => 'Old Name']));

        $content = $this->csv([[
            'external_ref' => 'EXT-1',
            'part_name' => 'New Name',
            'part_code' => 'BRK-001',
            'category' => 'brakes',
            'unit' => 'set',
            'purchase_price' => '350',
            'mrp' => '450',
            'labor_charge' => '80',
            'current_stock_qty' => '25',
            'reorder_level' => '10',
            'created_at' => '2026-01-01 10:00:00',
            'updated_at' => '2026-01-02 10:00:00',
        ]]);

        $token = $this->upload($content, 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $this->assertSame(1, SparePart::count());
        $this->assertDatabaseHas('spare_parts', ['external_ref' => 'EXT-1', 'part_name' => 'New Name']);

        $part = SparePart::first();
        $this->assertSame('2026-01-02 10:00:00', $part->updated_at->format('Y-m-d H:i:s'));
    }

    public function test_headers_are_matched_case_insensitively()
    {
        $content = "Part_Name,Part_Code,Category,Unit,Purchase_Price,MRP\nChain Kit,CHN-003,brakes,pcs,250,320\n";

        $token = $this->upload($content, 'create_or_update');

        $this->assertPreviewOk($token);
        $this->confirm($token)->assertOk();

        $this->assertDatabaseHas('spare_parts', ['part_code' => 'CHN-003', 'part_name' => 'Chain Kit']);
    }

    // ------------------------------------------------------------- duplicate handling

    public function test_duplicate_rows_inside_file_are_rejected()
    {
        $content = $this->csv([
            ['part_name' => 'A', 'part_code' => 'BRK-001', 'category' => 'brakes', 'unit' => 'set', 'purchase_price' => '10', 'mrp' => '20'],
            ['part_name' => 'B', 'part_code' => 'BRK-001', 'category' => 'brakes', 'unit' => 'set', 'purchase_price' => '10', 'mrp' => '20'],
        ]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('Duplicate value');

        $this->confirm($token)->assertOk();

        $this->assertSame(1, SparePart::count());
    }

    // ------------------------------------------------------------- validation

    public function test_invalid_unit_is_rejected()
    {
        $content = $this->csv([[
            'part_name' => 'Chain Kit',
            'part_code' => 'CHN-003',
            'category' => 'brakes',
            'unit' => 'box',
            'purchase_price' => '250',
            'mrp' => '320',
        ]]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('must be one of');

        $this->confirm($token)->assertOk();
        $this->assertSame(0, SparePart::count());
    }

    public function test_negative_stock_is_rejected()
    {
        $content = $this->csv([[
            'part_name' => 'Chain Kit',
            'part_code' => 'CHN-003',
            'category' => 'brakes',
            'unit' => 'pcs',
            'purchase_price' => '250',
            'mrp' => '320',
            'current_stock_qty' => '-5',
            'reorder_level' => '10',
        ]]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('Current stock qty must be at least 0');

        $this->confirm($token)->assertOk();
        $this->assertSame(0, SparePart::count());
    }

    public function test_missing_required_column_is_a_fatal_structure_error()
    {
        // No part_code column at all.
        $content = $this->csv([['part_name' => 'Chain Kit']]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('Missing required column');

        $this->confirm($token)->assertOk();
        $this->assertSame(0, SparePart::count());
    }

    // ------------------------------------------------------------- modes

    public function test_create_only_mode_skips_existing_parts()
    {
        SparePart::create($this->partFields(['part_code' => 'BRK-001', 'part_name' => 'Old']));

        $content = $this->csv([[
            'part_name' => 'New',
            'part_code' => 'BRK-001',
            'category' => 'brakes',
            'unit' => 'set',
            'purchase_price' => '10',
            'mrp' => '20',
        ]]);

        $token = $this->upload($content, 'create_only');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('Create only');

        $this->confirm($token)->assertOk();

        $this->assertSame(1, SparePart::count());
        $this->assertDatabaseHas('spare_parts', ['part_code' => 'BRK-001', 'part_name' => 'Old']);
    }

    public function test_create_or_update_mode_updates_existing_part_by_part_code()
    {
        SparePart::create($this->partFields(['part_code' => 'BRK-001', 'part_name' => 'Old']));

        $content = $this->csv([[
            'part_name' => 'New',
            'part_code' => 'BRK-001',
            'category' => 'brakes',
            'unit' => 'set',
            'purchase_price' => '10',
            'mrp' => '20',
        ]]);

        $token = $this->upload($content, 'create_or_update');

        $this->actingAs($this->user)
            ->get(route('import-export.preview.show', $token))
            ->assertOk()
            ->assertSee('To update');

        $this->confirm($token)->assertOk();

        $this->assertSame(1, SparePart::count());
        $this->assertDatabaseHas('spare_parts', ['part_code' => 'BRK-001', 'part_name' => 'New']);
    }
}
