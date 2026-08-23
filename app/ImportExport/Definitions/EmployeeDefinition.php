<?php

namespace App\ImportExport\Definitions;

use App\ImportExport\AbstractImportDefinition;
use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Employee — export-only definition.
 *
 * Employees stay out of the import hub (they are reference targets for Job Card
 * imports) but the Employees data table still needs the shared "Export
 * selected" CSV action, so the definition is registered under config
 * "export_entities" only. The reference hooks (importKeys/resolveCandidates)
 * are kept so Job Card imports can resolve an assigned technician or a
 * supervisor by external_ref.
 */
class EmployeeDefinition extends AbstractImportDefinition
{
    public function key(): string
    {
        return 'employees';
    }

    public function label(): string
    {
        return 'Employees';
    }

    public function description(): ?string
    {
        return 'Export-only entity for the Employees data table.';
    }

    public function columns(): array
    {
        return [
            ['header' => 'external_ref', 'field' => 'external_ref', 'label' => 'External Ref', 'required' => false, 'type' => 'string', 'max_length' => 255],
            ['header' => 'name', 'field' => 'name', 'label' => 'Name', 'required' => false, 'type' => 'string', 'max_length' => 255],
            ['header' => 'role', 'field' => 'role', 'label' => 'Role', 'required' => false, 'type' => 'string', 'max_length' => 50],
            ['header' => 'mobile', 'field' => 'mobile', 'label' => 'Mobile', 'required' => false, 'type' => 'string', 'max_length' => 20],
            ['header' => 'specialization', 'field' => 'specialization', 'label' => 'Specialization', 'required' => false, 'type' => 'string', 'max_length' => 100],
            ['header' => 'active', 'field' => 'active', 'label' => 'Active', 'required' => false, 'type' => 'boolean'],
            ['header' => 'join_date', 'field' => 'join_date', 'label' => 'Join Date', 'required' => false, 'type' => 'date'],
            ['header' => 'created_at', 'field' => 'created_at', 'label' => 'Created At', 'required' => false, 'type' => 'datetime'],
            ['header' => 'updated_at', 'field' => 'updated_at', 'label' => 'Updated At', 'required' => false, 'type' => 'datetime'],
        ];
    }

    public function exportColumns(): array
    {
        return [
            'external_ref' => 'external_ref',
            'name' => 'name',
            'role' => 'role',
            'mobile' => 'mobile',
            'specialization' => 'specialization',
            'active' => 'active',
            'join_date' => 'join_date',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }

    public function importKeys(): array
    {
        return ['external_ref', 'name'];
    }

    public function resolveCandidates(array $data): Collection
    {
        return $this->resolveByKeys(Employee::class, $data, $this->importKeys());
    }

    public function exportQuery(array $filters = [])
    {
        return Employee::query()
            ->orderBy('name')
            ->orderBy('id');
    }

    public function exportFileName(): string
    {
        return 'employees';
    }
}
