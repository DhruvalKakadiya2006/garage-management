<?php

namespace App\ImportExport\Definitions;

use App\ImportExport\AbstractImportDefinition;
use App\Models\VehicleModel;

/**
 * Vehicle Model — export-only definition.
 *
 * Vehicle models are managed inside the Vehicle Catalog page (one selection
 * scope per brand) and are never importable, so the definition lives under
 * config "export_entities" and drives the nested "Export selected" action.
 */
class VehicleModelDefinition extends AbstractImportDefinition
{
    public function key(): string
    {
        return 'vehicle_models';
    }

    public function label(): string
    {
        return 'Vehicle Models';
    }

    public function description(): ?string
    {
        return 'Export-only entity for the Vehicle Catalog model tables.';
    }

    public function columns(): array
    {
        return [
            ['header' => 'external_ref', 'field' => 'external_ref', 'label' => 'External Ref', 'required' => false, 'type' => 'string', 'max_length' => 255],
            ['header' => 'brand', 'field' => 'vehicleBrand.name', 'label' => 'Brand', 'required' => false, 'type' => 'string', 'max_length' => 255],
            ['header' => 'name', 'field' => 'name', 'label' => 'Model Name', 'required' => false, 'type' => 'string', 'max_length' => 255],
            ['header' => 'battery_type', 'field' => 'battery_type', 'label' => 'Battery Type', 'required' => false, 'type' => 'string', 'max_length' => 50],
            ['header' => 'created_at', 'field' => 'created_at', 'label' => 'Created At', 'required' => false, 'type' => 'datetime'],
            ['header' => 'updated_at', 'field' => 'updated_at', 'label' => 'Updated At', 'required' => false, 'type' => 'datetime'],
        ];
    }

    public function exportColumns(): array
    {
        return [
            'external_ref' => 'external_ref',
            'brand' => 'vehicleBrand.name',
            'name' => 'name',
            'battery_type' => 'battery_type',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }

    public function exportQuery(array $filters = [])
    {
        return VehicleModel::with('vehicleBrand')
            ->orderBy('name')
            ->orderBy('id');
    }

    public function exportFileName(): string
    {
        return 'vehicle-models';
    }
}
