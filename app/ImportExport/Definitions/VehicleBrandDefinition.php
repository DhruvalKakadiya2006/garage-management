<?php

namespace App\ImportExport\Definitions;

use App\ImportExport\AbstractImportDefinition;
use App\Models\VehicleBrand;
use Illuminate\Support\Collection;

/**
 * Vehicle Brand — a small reference/master-data entity wired to the reusable
 * import/export pipeline. Demonstrates that the foundation is generic before
 * larger entities (customers, job cards) build on it.
 */
class VehicleBrandDefinition extends AbstractImportDefinition
{
    public function key(): string
    {
        return 'vehicle_brands';
    }

    public function label(): string
    {
        return 'Vehicle Brands';
    }

    public function description(): ?string
    {
        return 'Master data for vehicle brands. Reference entity for the reusable import/export pipeline.';
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
                'header' => 'name',
                'field' => 'name',
                'label' => 'Name',
                'required' => true,
                'type' => 'string',
                'max_length' => 255,
                'unique_in_file' => true,
            ],
        ];
    }

    public function importKeys(): array
    {
        return ['external_ref', 'name'];
    }

    public function resolveCandidates(array $data): Collection
    {
        return $this->resolveByKeys(VehicleBrand::class, $data, $this->importKeys());
    }

    public function applyCreate(array $data): object
    {
        return VehicleBrand::create($data);
    }

    public function applyUpdate(object $record, array $data): object
    {
        $record->update($data);

        return $record;
    }

    public function exportColumns(): array
    {
        return [
            'external_ref' => 'external_ref',
            'name' => 'name',
        ];
    }

    public function exportQuery(array $filters = [])
    {
        return VehicleBrand::query()->orderBy('name');
    }

    public function exportFileName(): string
    {
        return 'vehicle-brands';
    }

    public function templateRows(): array
    {
        return [
            ['external_ref' => 'VB-1001', 'name' => 'Ola Electric'],
            ['external_ref' => 'VB-1002', 'name' => 'Ather Energy'],
        ];
    }
}