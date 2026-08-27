<?php

namespace App\ImportExport\Definitions;

use App\ImportExport\AbstractImportDefinition;
use App\Models\LabourMaster;

/**
 * Labour Master — export-only definition.
 *
 * Registered under config "export_entities", so the shared ExportService drives
 * the data-table "Export selected" action but Labour Services are never offered
 * for import in the hub.
 */
class LabourMasterDefinition extends AbstractImportDefinition
{
    public function key(): string
    {
        return 'labour_masters';
    }

    public function label(): string
    {
        return 'Labour Services';
    }

    public function description(): ?string
    {
        return 'Export-only entity for the Labour Services data table.';
    }

    public function columns(): array
    {
        return [
            ['header' => 'external_ref', 'field' => 'external_ref', 'label' => 'External Ref', 'required' => false, 'type' => 'string', 'max_length' => 255],
            ['header' => 'service_name', 'field' => 'service_name', 'label' => 'Service Name', 'required' => false, 'type' => 'string', 'max_length' => 255],
            ['header' => 'category', 'field' => 'category', 'label' => 'Category', 'required' => false, 'type' => 'string', 'max_length' => 100],
            ['header' => 'standard_rate', 'field' => 'standard_rate', 'label' => 'Standard Rate', 'required' => false, 'type' => 'decimal'],
            ['header' => 'taxable', 'field' => 'taxable', 'label' => 'Taxable', 'required' => false, 'type' => 'boolean'],
            ['header' => 'gst_rate', 'field' => 'gst_rate', 'label' => 'GST Rate', 'required' => false, 'type' => 'decimal'],
            ['header' => 'hsn_sac_code', 'field' => 'hsn_sac_code', 'label' => 'HSN/SAC Code', 'required' => false, 'type' => 'string', 'max_length' => 20],
            ['header' => 'created_at', 'field' => 'created_at', 'label' => 'Created At', 'required' => false, 'type' => 'datetime'],
            ['header' => 'updated_at', 'field' => 'updated_at', 'label' => 'Updated At', 'required' => false, 'type' => 'datetime'],
        ];
    }

    public function exportColumns(): array
    {
        return [
            'external_ref' => 'external_ref',
            'service_name' => 'service_name',
            'category' => 'category',
            'standard_rate' => 'standard_rate',
            'taxable' => 'taxable',
            'gst_rate' => 'gst_rate',
            'hsn_sac_code' => 'hsn_sac_code',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }

    public function exportQuery(array $filters = [])
    {
        return LabourMaster::query()
            ->orderBy('service_name')
            ->orderBy('id');
    }

    public function exportFileName(): string
    {
        return 'labour-masters';
    }
}
