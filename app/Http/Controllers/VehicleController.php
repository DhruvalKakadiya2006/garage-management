<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index()
    {
        $vehicles = Vehicle::with('customer')->latest()->paginate(15);
        return view('vehicles.index', compact('vehicles'));
    }

    public function create(Request $request)
    {
        $customers = Customer::orderBy('display_name')->get();
        $selectedCustomerId = $request->integer('customer_id') ?: null;
        return view('vehicles.create', compact('customers', 'selectedCustomerId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'is_non_rto' => 'boolean',
            'registration_no' => 'nullable|required_unless:is_non_rto,1|string|max:50|unique:vehicles,registration_no',
            'chassis_no' => 'nullable|string|max:100',
            'motor_battery_serial_no' => 'nullable|string|max:100',
            'vehicle_brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'battery_type' => 'nullable|in:' . implode(',', array_keys(config('garage.option_lists.vehicle_battery_type'))),
            'current_odometer_km' => 'nullable|integer|min:0',
            'color' => 'nullable|string|max:50',
            'remarks' => 'nullable|string',
        ]);

        Vehicle::create($validated);

        return redirect()->route('vehicles.index')->with('success', 'Vehicle added successfully.');
    }

    public function edit(Vehicle $vehicle)
    {
        $customers = Customer::orderBy('display_name')->get();
        return view('vehicles.edit', compact('vehicle', 'customers'));
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'is_non_rto' => 'boolean',
            'registration_no' => 'nullable|required_unless:is_non_rto,1|string|max:50|unique:vehicles,registration_no,' . $vehicle->id,
            'chassis_no' => 'nullable|string|max:100',
            'motor_battery_serial_no' => 'nullable|string|max:100',
            'vehicle_brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'battery_type' => 'nullable|in:' . implode(',', array_keys(config('garage.option_lists.vehicle_battery_type'))),
            'current_odometer_km' => 'nullable|integer|min:0',
            'color' => 'nullable|string|max:50',
            'remarks' => 'nullable|string',
        ]);

        $vehicle->update($validated);

        return redirect()->route('vehicles.index')->with('success', 'Vehicle updated successfully.');
    }

    public function destroy(Vehicle $vehicle)
    {
        try {
            $vehicle->delete();
            return redirect()->route('vehicles.index')->with('success', 'Vehicle deleted.');
        } catch (QueryException $e) {
            return back()->with('error', 'Cannot delete — this vehicle has existing job cards or invoices linked to it.');
        }
    }
}
