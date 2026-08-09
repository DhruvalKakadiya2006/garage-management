<?php

namespace App\Http\Controllers;

use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleModelController extends Controller
{
    public function create(Request $request)
    {
        $brands = VehicleBrand::orderBy('name')->get();
        $selectedBrandId = $request->integer('brand_id') ?: null;
        return view('vehicle_models.create', compact('brands', 'selectedBrandId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vehicle_brand_id' => 'required|exists:vehicle_brands,id',
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vehicle_models', 'name')
                    ->where(fn ($query) => $query->where('vehicle_brand_id', $request->input('vehicle_brand_id'))),
            ],
            'battery_type' => 'nullable|in:' . implode(',', array_keys(config('garage.option_lists.vehicle_battery_type'))),
        ]);

        VehicleModel::create($validated);

        return redirect()->route('vehicle-catalog.index')->with('success', 'Model added successfully.');
    }

    public function edit(VehicleModel $vehicleModel)
    {
        $brands = VehicleBrand::orderBy('name')->get();
        return view('vehicle_models.edit', compact('vehicleModel', 'brands'));
    }

    public function update(Request $request, VehicleModel $vehicleModel)
    {
        $validated = $request->validate([
            'vehicle_brand_id' => 'required|exists:vehicle_brands,id',
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vehicle_models', 'name')
                    ->where(fn ($query) => $query->where('vehicle_brand_id', $request->input('vehicle_brand_id')))
                    ->ignore($vehicleModel->id),
            ],
            'battery_type' => 'nullable|in:' . implode(',', array_keys(config('garage.option_lists.vehicle_battery_type'))),
        ]);

        $vehicleModel->update($validated);

        return redirect()->route('vehicle-catalog.index')->with('success', 'Model updated successfully.');
    }

    public function destroy(VehicleModel $vehicleModel)
    {
        try {
            $vehicleModel->delete();
            return redirect()->route('vehicle-catalog.index')->with('success', 'Model deleted.');
        } catch (QueryException $e) {
            return back()->with('error', 'Cannot delete — this model is referenced by existing records.');
        }
    }
}
