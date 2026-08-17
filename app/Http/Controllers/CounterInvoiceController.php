<?php

namespace App\Http\Controllers;

use App\Models\CounterInvoice;
use App\Models\Customer;
use App\Models\SparePart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\GstCalculator;
use App\Services\PartPriceResolver;
use App\Services\StockValidator;

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
        $customers = Customer::orderBy('display_name')->get();
        $spareParts = SparePart::orderBy('part_name')->get();
        
        return view('counter_invoices.create', compact('customers', 'spareParts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_date' => 'required|date',
            'customer_id' => 'nullable|exists:customers,id|required_without_all:walkin_name,walkin_mobile',
            'walkin_name' => 'nullable|string|max:255',
            'walkin_mobile' => 'nullable|string|max:20',
            'payment_mode' => 'nullable|in:' . implode(',', array_keys(config('garage.option_lists.payment_mode'))),
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'gst_applicable' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.spare_part_id' => 'required|exists:spare_parts,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ], [
            'customer_id.required_without_all' => 'Select a customer or enter a walk-in name / mobile number.',
        ]);

        // Stock check before any writes so stock can never go negative
        $stockErrors = app(StockValidator::class)->validate($request->input('items', []), null, 'items');
        if (!empty($stockErrors)) {
            return back()->withErrors($stockErrors)->withInput();
        }

        // MRP is always taken from the part master, never from the client submission
        $items = app(PartPriceResolver::class)->priceLines($request->items ?? []);

        DB::beginTransaction();
        try {
            $calculations = app(GstCalculator::class)->calculate(
                $this->buildGstLines($items),
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

            $this->createItems($invoice, $items);

            DB::commit();
            return redirect()->route('counter-invoices.show', $invoice)->with('success', 'Counter Invoice created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return back()->withInput()->withErrors(['error' => 'Something went wrong saving this record. Please try again.']);
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
        $customers = Customer::orderBy('display_name')->get();
        $spareParts = SparePart::orderBy('part_name')->get();
        
        return view('counter_invoices.edit', compact('counterInvoice', 'customers', 'spareParts'));
    }

    public function update(Request $request, CounterInvoice $counterInvoice)
    {
        $validated = $request->validate([
            'invoice_date' => 'required|date',
            'customer_id' => 'nullable|exists:customers,id|required_without_all:walkin_name,walkin_mobile',
            'walkin_name' => 'nullable|string|max:255',
            'walkin_mobile' => 'nullable|string|max:20',
            'payment_mode' => 'nullable|in:' . implode(',', array_keys(config('garage.option_lists.payment_mode'))),
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'gst_applicable' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.spare_part_id' => 'required|exists:spare_parts,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ], [
            'customer_id.required_without_all' => 'Select a customer or enter a walk-in name / mobile number.',
        ]);

        // Stock check accounting for quantity this invoice already reserves
        $stockErrors = app(StockValidator::class)->validate($request->input('items', []), $counterInvoice, 'items');
        if (!empty($stockErrors)) {
            return back()->withErrors($stockErrors)->withInput();
        }

        // MRP is always taken from the part master, never from the client submission
        $items = app(PartPriceResolver::class)->priceLines($request->items ?? []);

        DB::beginTransaction();
        try {
            $calculations = app(GstCalculator::class)->calculate(
                $this->buildGstLines($items),
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

            $this->createItems($counterInvoice, $items);

            DB::commit();
            return redirect()->route('counter-invoices.show', $counterInvoice)->with('success', 'Counter Invoice updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return back()->withInput()->withErrors(['error' => 'Something went wrong saving this record. Please try again.']);
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
            Log::error($e);
            return back()->withErrors(['error' => 'Something went wrong deleting this record. Please try again.']);
        }
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

    private function buildGstLines(array $items): array
    {
        $partIds = collect($items)->pluck('spare_part_id')->unique();
        $parts = $partIds->isEmpty() ? collect() : SparePart::whereIn('id', $partIds)->get()->keyBy('id');

        $lines = [];
        foreach ($items as $item) {
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
