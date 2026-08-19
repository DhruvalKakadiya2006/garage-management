<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BulkDeletesRecords;
use App\Models\SparePart;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class SparePartController extends Controller
{
    use BulkDeletesRecords;

    protected function bulkDeleteModel(): string
    {
        return SparePart::class;
    }

    public function index(Request $request)
    {
        $params = ['tab' => 'parts'];

        if ($request->boolean('low_stock')) {
            $params['low_stock'] = 1;
        }

        return redirect()->route('inventory.index', $params);
    }

    /**
     * Search spare parts by name or code for the Assign & Bill / Counter
     * Invoice line pickers. Returns a small bounded result set so pages never
     * embed the entire parts catalog; empty queries return nothing.
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q'));

        if ($q === '') {
            return response()->json([]);
        }

        $parts = SparePart::query()
            ->where(function ($query) use ($q) {
                $query->where('part_name', 'like', "%{$q}%")
                    ->orWhere('part_code', 'like', "%{$q}%");
            })
            ->orderBy('part_name')
            ->limit(20)
            ->get();

        return response()->json($parts->map(fn ($part) => [
            'id' => (string) $part->id,
            'part_name' => $part->part_name,
            'part_code' => $part->part_code,
            'mrp' => (float) $part->mrp,
            'labor_charge' => (float) $part->labor_charge,
            'gst_rate' => (float) $part->gst_rate,
            'current_stock_qty' => (int) $part->current_stock_qty,
            'label' => $part->part_name.' ('.$part->part_code.')',
        ]));
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
            'unit' => 'required|in:'.implode(',', array_keys(config('garage.option_lists.spare_part_unit'))),
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

        return redirect()->route('inventory.index', ['tab' => 'parts'])->with('success', 'Spare part added successfully.');
    }

    public function edit(SparePart $sparePart)
    {
        return view('spare_parts.edit', compact('sparePart'));
    }

    public function update(Request $request, SparePart $sparePart)
    {
        $validated = $request->validate([
            'part_name' => 'required|string|max:255',
            'part_code' => 'required|string|max:100|unique:spare_parts,part_code,'.$sparePart->id,
            'category' => 'nullable|string|max:100',
            'compatible_vehicle_brand' => 'nullable|string|max:100',
            'unit' => 'required|in:'.implode(',', array_keys(config('garage.option_lists.spare_part_unit'))),
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

        return redirect()->route('inventory.index', ['tab' => 'parts'])->with('success', 'Spare part updated successfully.');
    }

    public function destroy(SparePart $sparePart)
    {
        try {
            $sparePart->delete();

            return redirect()->route('inventory.index', ['tab' => 'parts'])->with('success', 'Spare part deleted.');
        } catch (QueryException $e) {
            return back()->with('error', 'Cannot delete — this part has existing job cards or invoices linked to it.');
        }
    }
}
