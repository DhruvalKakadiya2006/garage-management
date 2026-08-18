<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class CustomerVehicleController extends Controller
{
    public function create(Request $request)
    {
        $brands = VehicleBrand::with('models')->orderBy('name')->get();
        $selectedCustomerId = old('customer_id', $request->integer('customer_id')) ?: null;
        $initialCustomer = $selectedCustomerId ? Customer::find($selectedCustomerId) : null;
        $initialCustomerText = $initialCustomer ? $initialCustomer->display_name.' — '.$initialCustomer->mobile : '';

        return view('vehicles.create', compact('brands', 'selectedCustomerId', 'initialCustomer', 'initialCustomerText'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'brand_id' => 'required|exists:vehicle_brands,id',
            'vehicle_model_id' => 'required|exists:vehicle_models,id',
            'is_non_rto' => 'boolean',
            'registration_no' => 'nullable|required_unless:is_non_rto,1|string|max:50|unique:vehicles,registration_no',
            'chassis_no' => 'nullable|string|max:100',
            'motor_battery_serial_no' => 'nullable|string|max:100',
            'current_odometer_km' => 'nullable|integer|min:0',
            'color' => 'nullable|string|max:50',
            'remarks' => 'nullable|string',
        ]);

        // The chosen model must actually belong to the chosen brand
        $model = VehicleModel::find($validated['vehicle_model_id']);
        if ($model->vehicle_brand_id != $validated['brand_id']) {
            return back()->withErrors(['brand_id' => 'The selected model does not belong to the selected brand.'])->withInput();
        }

        unset($validated['brand_id']);

        $vehicle = Vehicle::create($validated);

        return redirect()->route('customers.show', $vehicle->customer_id)->with('success', 'Vehicle added successfully.');
    }

    public function edit(Vehicle $vehicle)
    {
        $brands = VehicleBrand::with('models')->orderBy('name')->get();

        // Resolve the currently-selected model here (falling back to the old
        // input when returning from a failed validation) so the template never
        // has to query Eloquent directly.
        $currentModelId = old('vehicle_model_id', $vehicle->vehicle_model_id);
        $currentModel = $currentModelId ? VehicleModel::find($currentModelId) : null;

        return view('vehicles.edit', compact('vehicle', 'brands', 'currentModel'));
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        // Ownership is not reassignable from the edit form: customer_id is
        // deliberately excluded here so a vehicle (and its job card history)
        // can never be silently moved to another customer. Reassignment should
        // be a deliberate separate action if ever needed.
        $validated = $request->validate([
            'brand_id' => 'required|exists:vehicle_brands,id',
            'vehicle_model_id' => 'required|exists:vehicle_models,id',
            'is_non_rto' => 'boolean',
            'registration_no' => 'nullable|required_unless:is_non_rto,1|string|max:50|unique:vehicles,registration_no,'.$vehicle->id,
            'chassis_no' => 'nullable|string|max:100',
            'motor_battery_serial_no' => 'nullable|string|max:100',
            'current_odometer_km' => 'nullable|integer|min:0',
            'color' => 'nullable|string|max:50',
            'remarks' => 'nullable|string',
        ]);

        // The chosen model must actually belong to the chosen brand
        $model = VehicleModel::find($validated['vehicle_model_id']);
        if ($model->vehicle_brand_id != $validated['brand_id']) {
            return back()->withErrors(['brand_id' => 'The selected model does not belong to the selected brand.'])->withInput();
        }

        unset($validated['brand_id']);

        $vehicle->update($validated);

        return redirect()->route('customers.show', $vehicle->customer_id)->with('success', 'Vehicle updated successfully.');
    }

    public function destroy(Vehicle $vehicle)
    {
        try {
            $vehicle->delete();

            return redirect()->route('customers.index')->with('success', 'Vehicle deleted.');
        } catch (QueryException $e) {
            return back()->with('error', 'Cannot delete — this vehicle has existing job cards or invoices linked to it.');
        }
    }
}
