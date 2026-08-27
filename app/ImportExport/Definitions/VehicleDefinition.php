<?php

namespace App\ImportExport\Definitions;

use App\ImportExport\AbstractImportDefinition;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Vehicle reference entity for the import pipeline.
 *
 * Vehicles are deliberately NOT registered as a top-level entity — they are
 * managed through the Customers page and the Vehicle Catalog. This definition
 * exists only so Job Card imports can resolve a vehicle by external_ref or
 * registration_no. Its key never appears in URLs or the hub.
 */
class VehicleDefinition extends AbstractImportDefinition
{
    public function key(): string
    {
        return 'vehicles';
    }

    public function label(): string
    {
        return 'Vehicles';
    }

    public function description(): ?string
    {
        return 'Reference entity used to resolve vehicles referenced by Job Card imports. Not importable on its own.';
    }

    public function importKeys(): array
    {
        return ['external_ref', 'registration_no'];
    }

    public function resolveCandidates(array $data): Collection
    {
        return $this->resolveByKeys(Vehicle::class, $data, $this->importKeys());
    }

    public function exportQuery(array $filters = [])
    {
        return Vehicle::query()->whereRaw('1 = 0');
    }
}
