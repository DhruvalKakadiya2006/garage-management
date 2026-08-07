<?php

namespace App\Http\Controllers;

use App\Models\SparePart;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class SparePartController extends Controller
{
    public function index(Request $request)
    {
        $lowStock = $request->boolean('low_stock');

        $spareParts = SparePart::when($lowStock, function ($query) {
                $query->whereColumn('current_stock_qty', '<=', 'reorder_level');
            })
            ->latest()
            ->paginate(15);

        return view('spare_parts.index', compact('spareParts', 'lowStock'));
    }

    public function create()
    {
        return view('spare_parts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'part_name' => 'required|string|max:255',
            'part_code' => 'required|string|max:100|unique:spare_parts,part_code',
            'category' => 'nullable|string|max:100',
            'compatible_vehicle_brand' => 'nullable|string|max:100',
            'unit' => 'required|in:' . implode(',', array_keys(config('garage.option_lists.spare_part_unit'))),
            'purchase_price' => 'required|numeric|min:0',
            'mrp' => 'required|numeric|min:0',
            'labor_charge' => 'nullable|numeric|min:0',
            'hsn_code' => 'nullable|string|max:20',
            'gst_rate' => 'nullable|numeric|min:0|max:100',
            'current_stock_qty' => 'nullable|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
        ]);

        // Set defaults
        $validated['labor_charge'] = $validated['labor_charge'] ?? 0;
        $validated['current_stock_qty'] = $validated['current_stock_qty'] ?? 0;
        $validated['reorder_level'] = $validated['reorder_level'] ?? 0;

        SparePart::create($validated);

        return redirect()->route('spare-parts.index')->with('success', 'Spare part added successfully.');
    }

    public function edit(SparePart $sparePart)
    {
        return view('spare_parts.edit', compact('sparePart'));
    }

    public function update(Request $request, SparePart $sparePart)
    {
        $validated = $request->validate([
            'part_name' => 'required|string|max:255',
            'part_code' => 'required|string|max:100|unique:spare_parts,part_code,' . $sparePart->id,
            'category' => 'nullable|string|max:100',
            'compatible_vehicle_brand' => 'nullable|string|max:100',
            'unit' => 'required|in:' . implode(',', array_keys(config('garage.option_lists.spare_part_unit'))),
            'purchase_price' => 'required|numeric|min:0',
            'mrp' => 'required|numeric|min:0',
            'labor_charge' => 'nullable|numeric|min:0',
            'hsn_code' => 'nullable|string|max:20',
            'gst_rate' => 'nullable|numeric|min:0|max:100',
            'current_stock_qty' => 'nullable|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
        ]);

        // Set defaults
        $validated['labor_charge'] = $validated['labor_charge'] ?? 0;
        $validated['current_stock_qty'] = $validated['current_stock_qty'] ?? 0;
        $validated['reorder_level'] = $validated['reorder_level'] ?? 0;

        $sparePart->update($validated);

        return redirect()->route('spare-parts.index')->with('success', 'Spare part updated successfully.');
    }

    public function destroy(SparePart $sparePart)
    {
        try {
            $sparePart->delete();
            return redirect()->route('spare-parts.index')->with('success', 'Spare part deleted.');
        } catch (QueryException $e) {
            return back()->with('error', 'Cannot delete — this part has existing job cards or invoices linked to it.');
        }
    }
}
