<?php

namespace App\ImportExport\Definitions;

use App\ImportExport\AbstractImportDefinition;
use App\ImportExport\ProcessedRow;
use App\ImportExport\ValidationIssue;
use App\Models\SparePart;
use Illuminate\Support\Collection;

/**
 * Spare Part import/export.
 *
 * Columns mirror the current spare_parts schema (see the create migration and
 * the 2026_08_06 supplier_id drop). Machine-readable, stable headers double as
 * the import headers so an exported file is re-importable without manual
 * column editing.
 *
 * Category is restricted to a predefined list of spare part categories.
 * Matching for create-or-update uses external_ref then part_code. part_code is
 * unique in the database, so it gives an unambiguous match; external_ref is the
 * stable identifier a shop controls.
 *
 * Stock is validated on the row level (current_stock_qty and reorder_level are
 * constrained to be non-negative by a database check) — Laravel's min:0 rule
 * compares absolute size, so a negative value slips through it.
 *
 * Imports create/update SparePart rows only. Stock-level history and existing
 * job-card / invoice line items are untouched.
 */
class SparePartDefinition extends AbstractImportDefinition
{
    public function key(): string
    {
        return 'spare_parts';
    }

    public function label(): string
    {
        return 'Spare Parts';
    }

    public function description(): ?string
    {
        return 'Create or update spare parts from a CSV file. Existing job cards and invoices are never touched.';
    }

    public function categories(): array
    {
        return [
            'accessories' => 'Accessories',
            'battery-and-bms' => 'Battery & BMS',
            'brake-system' => 'Brake System',
            'brakes' => 'Brakes',
            'cables-and-wiring' => 'Cables & Wiring',
            'charger-and-charging' => 'Charger & Charging',
            'drive-system' => 'Drive System',
            'electrical' => 'Electrical',
            'fasteners-and-hardware' => 'Fasteners & Hardware',
            'instruments-and-meters' => 'Instruments & Meters',
            'lighting-and-horns' => 'Lighting & Horns',
            'motor-and-motor-parts' => 'Motor & Motor Parts',
            'suspension-and-steering' => 'Suspension & Steering',
            'switches-and-controls' => 'Switches & Controls',
            'wheels-tyres-and-bearings' => 'Wheels, Tyres & Bearings',
        ];
    }

    public function columns(): array
    {
        return [
            [
                'header' => 'external_ref',
                'field' => 'external_ref',
                'label' => 'External Ref',
                'required' => false,
                'type' => 'string',
                'max_length' => 255,
                'unique_in_file' => true,
            ],
            [
                'header' => 'part_name',
                'field' => 'part_name',
                'label' => 'Part Name',
                'required' => true,
                'type' => 'string',
                'max_length' => 255,
            ],
            [
                'header' => 'part_code',
                'field' => 'part_code',
                'label' => 'Part Code',
                'required' => true,
                'type' => 'string',
                'max_length' => 100,
                'unique_in_file' => true,
            ],
            [
                'header' => 'category',
                'field' => 'category',
                'label' => 'Category',
                'required' => true,
                'type' => 'enum',
                'enum' => $this->categories(),
            ],
            [
                'header' => 'unit',
                'field' => 'unit',
                'label' => 'Unit',
                'required' => true,
                'type' => 'enum',
                'enum' => array_keys(config('garage.option_lists.spare_part_unit')),
            ],
            [
                'header' => 'purchase_price',
                'field' => 'purchase_price',
                'label' => 'Purchase Price',
                'required' => true,
                'type' => 'decimal',
            ],
            [
                'header' => 'mrp',
                'field' => 'mrp',
                'label' => 'MRP',
                'required' => true,
                'type' => 'decimal',
            ],
            [
                'header' => 'labor_charge',
                'field' => 'labor_charge',
                'label' => 'Labor Charge',
                'required' => false,
                'type' => 'decimal',
            ],
            [
                'header' => 'hsn_code',
                'field' => 'hsn_code',
                'label' => 'HSN Code',
                'required' => false,
                'type' => 'string',
                'max_length' => 20,
            ],
            [
                'header' => 'gst_rate',
                'field' => 'gst_rate',
                'label' => 'GST Rate',
                'required' => false,
                'type' => 'decimal',
            ],
            [
                'header' => 'current_stock_qty',
                'field' => 'current_stock_qty',
                'label' => 'Current Stock',
                'required' => false,
                'type' => 'integer',
            ],
            [
                'header' => 'reorder_level',
                'field' => 'reorder_level',
                'label' => 'Reorder Level',
                'required' => false,
                'type' => 'integer',
            ],
            [
                'header' => 'created_at',
                'field' => 'created_at',
                'label' => 'Created At',
                'required' => false,
                'type' => 'datetime',
            ],
            [
                'header' => 'updated_at',
                'field' => 'updated_at',
                'label' => 'Updated At',
                'required' => false,
                'type' => 'datetime',
            ],
        ];
    }

    public function importKeys(): array
    {
        return ['external_ref', 'part_code'];
    }

    public function resolveCandidates(array $data): Collection
    {
        return $this->resolveByKeys(SparePart::class, $data, $this->importKeys());
    }

    public function validateRow(ProcessedRow $row): void
    {
        // The database constrains current_stock_qty to >= 0 (check constraint),
        // so enforce it here too — Laravel's min:0 compares absolute size and
        // lets negatives through.
        foreach (['current_stock_qty', 'reorder_level'] as $field) {
            $value = $row->data[$field] ?? null;
            if ($value !== null && (int) $value < 0) {
                $row->addIssue(ValidationIssue::error(
                    $row->rowNumber,
                    $field,
                    $row->data[$field] ?? null,
                    ucfirst(str_replace('_', ' ', $field)).' must be at least 0.'
                ));
            }
        }

        foreach (['purchase_price', 'mrp', 'labor_charge'] as $field) {
            $value = $row->data[$field] ?? null;
            if ($value !== null && (float) $value < 0) {
                $row->addIssue(ValidationIssue::error(
                    $row->rowNumber,
                    $field,
                    $row->data[$field] ?? null,
                    ucfirst(str_replace('_', ' ', $field)).' must be at least 0.'
                ));
            }
        }

        $gst = $row->data['gst_rate'] ?? null;
        if ($gst !== null && ((float) $gst < 0 || (float) $gst > 100)) {
            $row->addIssue(ValidationIssue::error(
                $row->rowNumber,
                'gst_rate',
                $row->data['gst_rate'] ?? null,
                'GST Rate must be between 0 and 100.'
            ));
        }
    }

    public function applyCreate(array $data): object
    {
        // Auto-generate part_code if not provided (prefix from category)
        $category = $data['category'] ?? '';
        $categoryPrefix = $category ? strtoupper(substr($category, 0, 3)) : 'PART';
        if (($data['part_code'] ?? null) === null) {
            $lastPart = SparePart::max('part_code');
            if ($lastPart && preg_match('/^[A-Z]{2,3}-\d+$/', $lastPart)) {
                $lastNum = (int) explode('-', $lastPart)[1];
                $newNum = sprintf('%03d', $lastNum + 1);
                $data['part_code'] = "{$categoryPrefix}-{$newNum}";
            } else {
                $data['part_code'] = "{$categoryPrefix}-001";
            }
        }

        $data = $this->normalizeDefaults($data);

        [$fill, $timestamps] = $this->splitTimestamps($data);

        $sparePart = SparePart::create($fill);

        $this->restoreTimestamps($sparePart, $timestamps);

        return $sparePart;
    }

    public function applyUpdate(object $record, array $data): object
    {
        // Auto-generate part_code if not provided (prefix from category)
        $category = $data['category'] ?? '';
        $categoryPrefix = $category ? strtoupper(substr($category, 0, 3)) : 'PART';
        if (($data['part_code'] ?? null) === null) {
            $lastPart = SparePart::max('part_code');
            if ($lastPart && preg_match('/^[A-Z]{2,3}-\d+$/', $lastPart)) {
                $lastNum = (int) explode('-', $lastPart)[1];
                $newNum = sprintf('%03d', $lastNum + 1);
                $data['part_code'] = "{$categoryPrefix}-{$newNum}";
            } else {
                $data['part_code'] = "{$categoryPrefix}-001";
            }
        }

        $data = $this->normalizeDefaults($data);

        [$fill, $timestamps] = $this->splitTimestamps($data);

        $record->update($fill);

        $this->restoreTimestamps($record, $timestamps);

        return $record;
    }

    public function exportColumns(): array
    {
        return [
            'external_ref' => 'external_ref',
            'part_name' => 'part_name',
            'part_code' => 'part_code',
            'category' => 'category',
            'unit' => 'unit',
            'purchase_price' => 'purchase_price',
            'mrp' => 'mrp',
            'labor_charge' => 'labor_charge',
            'hsn_code' => 'hsn_code',
            'gst_rate' => 'gst_rate',
            'current_stock_qty' => 'current_stock_qty',
            'reorder_level' => 'reorder_level',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }

    public function exportQuery(array $filters = [])
    {
        $query = SparePart::query();

        if (! empty($filters['low_stock'])) {
            $query->whereColumn('current_stock_qty', '<=', 'reorder_level');
        }

        return $query->orderBy('part_name')->orderBy('id');
    }

    public function exportFileName(): string
    {
        return 'spare-parts';
    }

    /**
     * Realistic sample rows that pass every validation rule and the stock
     * check constraint, so the template is directly importable.
     *
     * @return array<int, array<string, mixed>>
     */
    public function templateRows(): array
    {
        return [
            [
                'external_ref' => 'SP-0001',
                'part_name' => 'Brake Shoe Set',
                'part_code' => 'BRK-001',
                'category' => 'brakes',
                'unit' => 'set',
                'purchase_price' => '350.00',
                'mrp' => '450.00',
                'labor_charge' => '80.00',
                'hsn_code' => '87141090',
                'gst_rate' => '18.00',
                'current_stock_qty' => '25',
                'reorder_level' => '10',
                'created_at' => '2026-01-05 10:30:00',
                'updated_at' => '2026-01-05 10:30:00',
            ],
            [
                'external_ref' => 'SP-0002',
                'part_name' => 'Headlight Bulb 12V',
                'part_code' => 'LMP-002',
                'category' => 'electrical',
                'unit' => 'pcs',
                'purchase_price' => '120.00',
                'mrp' => '180.00',
                'labor_charge' => '30.00',
                'hsn_code' => '85391000',
                'gst_rate' => '18.00',
                'current_stock_qty' => '8',
                'reorder_level' => '15',
                'created_at' => '2026-02-10 09:15:00',
                'updated_at' => '2026-02-10 09:15:00',
            ],
        ];
    }

    /**
     * Spare part schema fields are NOT NULL with defaults. Keep them at their
     * default when a row leaves the cell blank, on both create and update.
     */
    private function normalizeDefaults(array $data): array
    {
        foreach (['labor_charge', 'current_stock_qty', 'reorder_level'] as $field) {
            if (($data[$field] ?? null) === null) {
                $data[$field] = 0;
            }
        }

        return $data;
    }
}