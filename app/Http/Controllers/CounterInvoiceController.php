<?php

namespace App\Http\Controllers;

use App\Models\CounterInvoice;
use App\Models\Customer;
use App\Models\SparePart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\GstCalculator;

class CounterInvoiceController extends Controller
{
    public function index()
    {
        $invoices = CounterInvoice::with('customer')
            ->withSum('payments', 'amount')
            ->latest()
            ->paginate(15);
        return view('counter_invoices.index', compact('invoices'));
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        $spareParts = SparePart::orderBy('part_name')->get();
        
        return view('counter_invoices.create', compact('customers', 'spareParts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_date' => 'required|date',
            'customer_id' => 'nullable|exists:customers,id',
            'walkin_name' => 'nullable|string|max:255',
            'walkin_mobile' => 'nullable|string|max:20',
            'payment_mode' => 'nullable|in:' . implode(',', array_keys(config('garage.option_lists.payment_mode'))),
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'gst_applicable' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.spare_part_id' => 'required|exists:spare_parts,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.mrp' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        // Stock check before any writes so stock can never go negative
        $stockErrors = $this->validateItemsStock($request, null);
        if (!empty($stockErrors)) {
            return back()->withErrors($stockErrors)->withInput();
        }

        DB::beginTransaction();
        try {
            $calculations = app(GstCalculator::class)->calculate(
                $this->buildGstLines($request),
                $request->has('gst_applicable'),
                $request->discount_percent ?? 0,
                $request->discount_amount ?? 0,
            );
            
            $invoice = CounterInvoice::create([
                'invoice_date' => $validated['invoice_date'],
                'customer_id' => $validated['customer_id'] ?? null,
                'walkin_name' => $validated['walkin_name'] ?? null,
                'walkin_mobile' => $validated['walkin_mobile'] ?? null,
                'payment_mode' => $validated['payment_mode'] ?? null,
                'discount_percent' => $calculations['discount_percent'],
                'discount_amount' => $calculations['discount_amount'],
                'gst_applicable' => $request->has('gst_applicable'),
                'cgst_amount' => $calculations['cgst_amount'],
                'sgst_amount' => $calculations['sgst_amount'],
                'round_off_amount' => $calculations['round_off_amount'],
                'grand_total' => $calculations['grand_total'],
            ]);

            $this->createItems($invoice, $request->items);

            DB::commit();
            return redirect()->route('counter-invoices.show', $invoice)->with('success', 'Counter Invoice created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Failed to create invoice: ' . $e->getMessage()]);
        }
    }

    public function show(CounterInvoice $counterInvoice)
    {
        $counterInvoice->load(['customer', 'items.sparePart']);
        return view('counter_invoices.show', compact('counterInvoice'));
    }

    public function edit(CounterInvoice $counterInvoice)
    {
        $counterInvoice->load('items');
        $customers = Customer::orderBy('name')->get();
        $spareParts = SparePart::orderBy('part_name')->get();
        
        return view('counter_invoices.edit', compact('counterInvoice', 'customers', 'spareParts'));
    }

    public function update(Request $request, CounterInvoice $counterInvoice)
    {
        $validated = $request->validate([
            'invoice_date' => 'required|date',
            'customer_id' => 'nullable|exists:customers,id',
            'walkin_name' => 'nullable|string|max:255',
            'walkin_mobile' => 'nullable|string|max:20',
            'payment_mode' => 'nullable|in:' . implode(',', array_keys(config('garage.option_lists.payment_mode'))),
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'gst_applicable' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.spare_part_id' => 'required|exists:spare_parts,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.mrp' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        // Stock check accounting for quantity this invoice already reserves
        $stockErrors = $this->validateItemsStock($request, $counterInvoice);
        if (!empty($stockErrors)) {
            return back()->withErrors($stockErrors)->withInput();
        }

        DB::beginTransaction();
        try {
            $calculations = app(GstCalculator::class)->calculate(
                $this->buildGstLines($request),
                $request->has('gst_applicable'),
                $request->discount_percent ?? 0,
                $request->discount_amount ?? 0,
            );

            $counterInvoice->update([
                'invoice_date' => $validated['invoice_date'],
                'customer_id' => $validated['customer_id'] ?? null,
                'walkin_name' => $validated['walkin_name'] ?? null,
                'walkin_mobile' => $validated['walkin_mobile'] ?? null,
                'payment_mode' => $validated['payment_mode'] ?? null,
                'discount_percent' => $calculations['discount_percent'],
                'discount_amount' => $calculations['discount_amount'],
                'gst_applicable' => $request->has('gst_applicable'),
                'cgst_amount' => $calculations['cgst_amount'],
                'sgst_amount' => $calculations['sgst_amount'],
                'round_off_amount' => $calculations['round_off_amount'],
                'grand_total' => $calculations['grand_total'],
            ]);

            // Delete existing items (stock returned via deleting hook), then recreate
            foreach ($counterInvoice->items as $item) {
                $item->delete();
            }

            $this->createItems($counterInvoice, $request->items);

            DB::commit();
            return redirect()->route('counter-invoices.show', $counterInvoice)->with('success', 'Counter Invoice updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Failed to update invoice: ' . $e->getMessage()]);
        }
    }

    public function destroy(CounterInvoice $counterInvoice)
    {
        DB::beginTransaction();
        try {
            // Delete items one-by-one so CounterInvoiceItem::deleting returns stock.
            // The counter_invoice_items FK is restrict, so the parent cannot be
            // deleted while any item rows remain — this guarantees stock is returned.
            foreach ($counterInvoice->items as $item) {
                $item->delete();
            }

            $counterInvoice->delete();

            DB::commit();
            return redirect()->route('counter-invoices.index')->with('success', 'Counter Invoice deleted.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to delete invoice: ' . $e->getMessage()]);
        }
    }

    private function validateItemsStock(Request $request, ?CounterInvoice $counterInvoice): array
    {
        $errors = [];
        $items = $request->input('items', []);

        if (empty($items)) {
            return $errors;
        }

        // Aggregate requested quantity per part across all lines in this request
        $usageByPart = [];
        foreach ($items as $index => $item) {
            $partId = (int) $item['spare_part_id'];
            $usageByPart[$partId] = ($usageByPart[$partId] ?? 0) + (int) $item['quantity'];
        }

        foreach ($usageByPart as $partId => $requestedQty) {
            $sparePart = SparePart::find($partId);
            if (!$sparePart) {
                continue;
            }

            $available = (int) $sparePart->current_stock_qty;
            if ($counterInvoice) {
                // Quantity this invoice already reserves for the same part
                $available += (int) $counterInvoice->items()->where('spare_part_id', $partId)->sum('quantity');
            }

            if ($requestedQty > $available) {
                $index = collect($items)->search(fn ($item) => (int) $item['spare_part_id'] === $partId);
                $errors["items.{$index}.quantity"] = "Insufficient stock for '{$sparePart->part_name}'. Requested {$requestedQty}, only {$available} available.";
            }
        }

        return $errors;
    }

    private function createItems(CounterInvoice $invoice, array $items)
    {
        foreach ($items as $item) {
            $itemDiscount = (float) ($item['discount_percent'] ?? 0);
            $mrp = (float) $item['mrp'];
            $quantity = (int) $item['quantity'];

            // Calculate line total: quantity * mrp, then apply item-level discount
            $lineSubtotal = $quantity * $mrp;
            $itemDiscountAmount = ($lineSubtotal * $itemDiscount) / 100;
            $lineTotal = $lineSubtotal - $itemDiscountAmount;

            $invoice->items()->create([
                'spare_part_id' => $item['spare_part_id'],
                'quantity' => $quantity,
                'mrp' => $mrp,
                'discount_percent' => $itemDiscount,
                'line_total' => $lineTotal,
            ]);
        }
    }

    private function buildGstLines(Request $request): array
    {
        $partIds = collect($request->items ?? [])->pluck('spare_part_id')->unique();
        $parts = $partIds->isEmpty() ? collect() : SparePart::whereIn('id', $partIds)->get()->keyBy('id');

        $lines = [];
        foreach ($request->items ?? [] as $item) {
            $itemDiscount = (float) ($item['discount_percent'] ?? 0);
            $mrp = (float) $item['mrp'];
            $quantity = (int) $item['quantity'];

            $lineSubtotal = $quantity * $mrp;
            $itemDiscountAmount = ($lineSubtotal * $itemDiscount) / 100;
            $lineTotal = $lineSubtotal - $itemDiscountAmount;

            $lines[] = [
                'line_total' => $lineTotal,
                'gst_rate' => $parts[$item['spare_part_id']]->gst_rate ?? null,
            ];
        }

        return $lines;
    }
}
