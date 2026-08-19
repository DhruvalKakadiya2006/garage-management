<?php

namespace App\ImportExport\Definitions;

use App\ImportExport\AbstractImportDefinition;
use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Employee reference entity for the import pipeline.
 *
 * Like vehicles, employees are not a top-level import/export entity. This
 * definition lets Job Card imports resolve an assigned technician or a
 * supervisor by external_ref. The key is kept private to the registry.
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
        return 'Reference entity used to resolve technicians/supervisors referenced by Job Card imports. Not importable on its own.';
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
        return Employee::query()->whereRaw('1 = 0');
    }
}