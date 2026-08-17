<?php

namespace App\Http\Controllers;

use App\Models\LabourMaster;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class LabourMasterController extends Controller
{
    public function index()
    {
        $labourMasters = LabourMaster::latest()->paginate(15);
        return view('labour_masters.index', compact('labourMasters'));
    }

    public function create()
    {
        return view('labour_masters.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_name' => 'required|string|max:255',
            'category' => 'required|in:' . implode(',', array_keys(config('garage.option_lists.labour_master_category'))),
            'standard_rate' => 'required|numeric|min:0',
            'estimated_time_minutes' => 'nullable|integer|min:0',
            'taxable' => 'boolean',
            'gst_rate' => 'nullable|numeric|min:0|max:100',
            'hsn_sac_code' => 'nullable|string|max:20',
        ]);

        // Ensure taxable is set (checkbox behavior)
        $validated['taxable'] = $request->has('taxable') ? true : false;

        // A non-taxable service must not carry a GST rate
        if (! $validated['taxable']) {
            $validated['gst_rate'] = null;
        }

        LabourMaster::create($validated);

        return redirect()->route('labour-masters.index')->with('success', 'Labour service added successfully.');
    }

    public function edit(LabourMaster $labourMaster)
    {
        return view('labour_masters.edit', compact('labourMaster'));
    }

    public function update(Request $request, LabourMaster $labourMaster)
    {
        $validated = $request->validate([
            'service_name' => 'required|string|max:255',
            'category' => 'required|in:' . implode(',', array_keys(config('garage.option_lists.labour_master_category'))),
            'standard_rate' => 'required|numeric|min:0',
            'estimated_time_minutes' => 'nullable|integer|min:0',
            'taxable' => 'boolean',
            'gst_rate' => 'nullable|numeric|min:0|max:100',
            'hsn_sac_code' => 'nullable|string|max:20',
        ]);

        // Ensure taxable is set (checkbox behavior)
        $validated['taxable'] = $request->has('taxable') ? true : false;

        // A non-taxable service must not carry a GST rate
        if (! $validated['taxable']) {
            $validated['gst_rate'] = null;
        }

        $labourMaster->update($validated);

        return redirect()->route('labour-masters.index')->with('success', 'Labour service updated successfully.');
    }

    public function destroy(LabourMaster $labourMaster)
    {
        try {
            $labourMaster->delete();
            return redirect()->route('labour-masters.index')->with('success', 'Labour service deleted.');
        } catch (QueryException $e) {
            return back()->with('error', 'Cannot delete — this labour service has existing job cards or invoices linked to it.');
        }
    }
}
