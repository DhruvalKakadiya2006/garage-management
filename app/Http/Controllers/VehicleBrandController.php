<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AppliesSearchFilterSort;
use App\Http\Controllers\Concerns\BulkDeletesRecords;
use App\Http\Controllers\Concerns\BulkExportsRecords;
use App\Http\Requests\FilterVehicleCatalogRequest;
use App\Models\VehicleBrand;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class VehicleBrandController extends Controller
{
    use AppliesSearchFilterSort;
    use BulkDeletesRecords;
    use BulkExportsRecords;

    protected function bulkDeleteModel(): string
    {
        return VehicleBrand::class;
    }

    protected function bulkExportEntity(): string
    {
        return 'vehicle_brands';
    }

    /**
     * Show the Vehicle Catalog: brands as expandable rows with their models.
     */
    public function index(FilterVehicleCatalogRequest $request)
    {
        $validated = $request->validated();

        $query = VehicleBrand::withCount('models')
            ->with('models');

        // Apply sorting
        $sortColumn = $validated['sort'] ?? 'name';
        $sortDirection = $validated['direction'] ?? 'asc';

        $query = $this->applySort(
            $query,
            $sortColumn,
            $sortDirection,
            ['name', 'models_count'],
            ['column' => 'name', 'direction' => 'asc']
        );

        $perPage = $this->getPerPage($request);
        $brands = $query->paginate($perPage)->withQueryString();

        $catalogData = $brands->getCollection()->map(fn (VehicleBrand $brand) => [
            'id' => (string) $brand->id,
            'name' => $brand->name,
            'models' => $brand->models->map(fn ($model) => [
                'id' => (string) $model->id,
                'name' => $model->name,
                'battery_type' => $model->battery_type,
            ])->values(),
        ])->values();

        return view('vehicle_catalog.index', compact('brands', 'catalogData'));
    }

    public function create()
    {
        return view('vehicle_brands.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:vehicle_brands,name',
        ]);

        VehicleBrand::create($validated);

        return redirect()->route('vehicle-catalog.index')->with('success', 'Brand added successfully.');
    }

    public function edit(VehicleBrand $vehicleBrand)
    {
        return view('vehicle_brands.edit', compact('vehicleBrand'));
    }

    public function update(Request $request, VehicleBrand $vehicleBrand)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:vehicle_brands,name,'.$vehicleBrand->id,
        ]);

        $vehicleBrand->update($validated);

        return redirect()->route('vehicle-catalog.index')->with('success', 'Brand updated successfully.');
    }

    public function destroy(VehicleBrand $vehicleBrand)
    {
        try {
            $vehicleBrand->delete();

            return redirect()->route('vehicle-catalog.index')->with('success', 'Brand deleted.');
        } catch (QueryException $e) {
            return back()->with('error', 'Cannot delete — this brand has models linked to it. Delete its models first.');
        }
    }
}
