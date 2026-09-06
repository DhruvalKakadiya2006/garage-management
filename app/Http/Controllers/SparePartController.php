<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AppliesSearchFilterSort;
use App\Http\Controllers\Concerns\BulkDeletesRecords;
use App\Http\Controllers\Concerns\BulkExportsRecords;
use App\Models\SparePart;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SparePartController extends Controller
{
    use AppliesSearchFilterSort;
    use BulkDeletesRecords;
    use BulkExportsRecords;

    protected function bulkDeleteModel(): string
    {
        return SparePart::class;
    }

    protected function bulkExportEntity(): string
    {
        return 'spare_parts';
    }

    public function index(Request $request)
    {
        return redirect()->route('inventory.index', ['tab' => 'parts']);
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

        // Escape LIKE wildcards (with an explicit ESCAPE clause) so a query
        // like "OIL-50%" matches that literal code only.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q);

        $parts = SparePart::query()
            ->where(function ($query) use ($escaped) {
                $query->whereRaw("part_name LIKE ? ESCAPE '\'", ["%{$escaped}%"])
                    ->orWhereRaw("part_code LIKE ? ESCAPE '\'", ["%{$escaped}%"]);
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
            'category' => 'nullable|string|max:100',
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

        // Auto-generate part_code if not provided (prefix from category).
        // The next number is the highest NUMERIC suffix for this prefix only
        // — a global lexicographic MAX would collide across categories and
        // roll over past "999".
        $categoryPrefix = $validated['category'] ? strtoupper(substr($validated['category'], 0, 3)) : 'PART';
        $validated['part_code'] = $this->nextPartCode($categoryPrefix);

        try {
            SparePart::create($validated);
        } catch (QueryException $e) {
            Log::warning('Spare part code collision.', ['part_code' => $validated['part_code'], 'error' => $e->getMessage()]);

            return back()->withInput()->withErrors([
                'error' => 'A spare part with the generated code '.$validated['part_code'].' already exists. Please submit again.',
            ]);
        }

        return redirect()->route('inventory.index', ['tab' => 'parts'])->with('success', 'Spare part added successfully.');
    }

    /**
     * Highest numeric suffix used by {$prefix}-### codes, plus one.
     */
    private function nextPartCode(string $prefix): string
    {
        $maxSuffix = (int) SparePart::query()
            ->where('part_code', 'like', $prefix.'-%')
            ->selectRaw('MAX(CAST(substr(part_code, ?) AS INTEGER)) as max_suffix', [strlen($prefix) + 2])
            ->value('max_suffix');

        return sprintf('%s-%03d', $prefix, max($maxSuffix, 0) + 1);
    }

    public function edit(SparePart $sparePart)
    {
        return view('spare_parts.edit', compact('sparePart'));
    }

    public function update(Request $request, SparePart $sparePart)
    {
        $validated = $request->validate([
            'part_name' => 'required|string|max:255',
            'part_code' => ['required', 'string', 'max:100', 'unique:spare_parts,part_code,'.$sparePart->id],
            'category' => 'nullable|string|max:100',
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
        $this->authorize('admin');

        try {
            $sparePart->delete();

            return redirect()->route('inventory.index', ['tab' => 'parts'])->with('success', 'Spare part deleted.');
        } catch (QueryException $e) {
            return back()->with('error', 'Cannot delete — this part has existing job cards or invoices linked to it.');
        }
    }
}
