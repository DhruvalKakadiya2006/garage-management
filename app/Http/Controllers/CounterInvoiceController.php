<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AppliesSearchFilterSort;
use App\Http\Controllers\Concerns\BulkDeletesRecords;
use App\Http\Controllers\Concerns\BulkExportsRecords;
use App\Http\Requests\FilterCounterInvoicesRequest;
use App\Models\CounterInvoice;
use App\Models\SparePart;
use App\Rules\DiscountWithinSubtotal;
use App\Services\GstCalculator;
use App\Services\PartPriceResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CounterInvoiceController extends Controller
{
    use AppliesSearchFilterSort;
    use BulkDeletesRecords;
    use BulkExportsRecords;

    protected function bulkDeleteModel(): string
    {
        return CounterInvoice::class;
    }

    protected function bulkExportEntity(): string
    {
        return 'counter_invoices';
    }

    /**
     * Counter Invoices cannot be plain-deleted: the counter_invoice_items FK
     * is restrict, so item rows must be deleted one-by-one first (which
     * returns stock to inventory via the CounterInvoiceItem::deleting boot
     * event) before the parent. The CounterInvoice::deleting boot event cleans
     * up related payments.
     */
    protected function bulkDeleteDeleter(): ?callable
    {
        return function (CounterInvoice $counterInvoice) {
            DB::transaction(function () use ($counterInvoice) {
                foreach ($counterInvoice->items as $item) {
                    $item->delete();
                }
                $counterInvoice->delete();
            });
        };
    }

    public function index(FilterCounterInvoicesRequest $request)
    {
        $validated = $request->validated();

        $query = CounterInvoice::with('customer')
            ->withSum('payments', 'amount');

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('invoice_date', '>=', $validated['from_date']);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('invoice_date', '<=', $validated['to_date']);
        }

        // Filter by payment status
        if ($request->filled('payment_status') && $validated['payment_status'] !== 'all') {
            $status = $validated['payment_status'];
            if ($status === 'paid') {
                $query->whereRaw('COALESCE(grand_total, 0) > 0 AND COALESCE(grand_total, 0) <= (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.payable_id = counter_invoices.id AND payments.payable_type = ? AND payments.deleted_at IS NULL)', [CounterInvoice::class]);
            } elseif ($status === 'partially_paid') {
                $query->whereRaw('COALESCE(grand_total, 0) > (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.payable_id = counter_invoices.id AND payments.payable_type = ? AND payments.deleted_at IS NULL) AND (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.payable_id = counter_invoices.id AND payments.payable_type = ? AND payments.deleted_at IS NULL) > 0', [CounterInvoice::class, CounterInvoice::class]);
            } elseif ($status === 'unpaid') {
                $query->whereRaw('COALESCE(grand_total, 0) > (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.payable_id = counter_invoices.id AND payments.payable_type = ? AND payments.deleted_at IS NULL) AND (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.payable_id = counter_invoices.id AND payments.payable_type = ? AND payments.deleted_at IS NULL) = 0', [CounterInvoice::class, CounterInvoice::class]);
            }
        }

        // Filter by customer type
        if ($request->filled('customer_type') && $validated['customer_type'] !== 'all') {
            if ($validated['customer_type'] === 'registered') {
                $query->whereNotNull('customer_id');
            } elseif ($validated['customer_type'] === 'walk_in') {
                $query->whereNull('customer_id');
            }
        }

        // Apply sorting
        $sortColumn = $validated['sort'] ?? 'invoice_date';
        $sortDirection = $validated['direction'] ?? 'desc';

        if ($sortColumn === 'payment_status_label') {
            // Sort by payment status (computed field)
            $query->orderByRaw('
                CASE 
                    WHEN COALESCE(grand_total, 0) <= (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.payable_id = counter_invoices.id AND payments.payable_type = "'.addslashes(CounterInvoice::class).'" AND payments.deleted_at IS NULL) THEN 1
                    WHEN (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.payable_id = counter_invoices.id AND payments.payable_type = "'.addslashes(CounterInvoice::class).'" AND payments.deleted_at IS NULL) > 0 THEN 2
                    ELSE 3
                END '.$sortDirection
            );
        } else {
            $query = $this->applySort(
                $query,
                $sortColumn,
                $sortDirection,
                ['invoice_date', 'invoice_no', 'grand_total'],
                ['column' => 'invoice_date', 'direction' => 'desc']
            );
        }

        $perPage = $this->getPerPage($request);
        $invoices = $query->paginate($perPage)->withQueryString();

        return view('counter_invoices.index', compact('invoices'));
    }

    public function create()
    {
        return view('counter_invoices.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_date' => 'required|date',
            'customer_id' => 'nullable|exists:customers,id|required_without_all:walkin_name,walkin_mobile',
            'walkin_name' => 'nullable|string|max:255',
            'walkin_mobile' => 'nullable|string|max:20',
            'payment_mode' => 'nullable|in:'.implode(',', array_keys(config('garage.option_lists.payment_mode'))),
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'gst_applicable' => 'boolean',
            'items' => 'required|array|min:1|max:50',
            'items.*.spare_part_id' => 'required|exists:spare_parts,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ], [
            'customer_id.required_without_all' => 'Select a customer or enter a walk-in name / mobile number.',
        ]);

        DB::beginTransaction();
        try {
            // MRP is always taken from the part master, never from the client submission
            $items = app(PartPriceResolver::class)->priceLines($validated['items']);

            // STOCK VALIDATION DISABLED: No stock checking because Suppliers module is not integrated
            // Uncomment below to re-enable when Suppliers module is integrated:
            //
            // $stockErrors = app(StockValidator::class)->validate(
            //     $items,
            //     null,
            //     'items'
            // );
            // if (! empty($stockErrors)) {
            //     DB::rollBack();
            //     return back()->withErrors($stockErrors)->withInput();
            // }

            // The subtotal is only known once the lines are resolved. Reject a
            // discount (percent-derived or flat amount) that would exceed it,
            // otherwise GstCalculator yields a negative grand_total.
            $lines = $this->buildGstLines($items);
            $subtotal = collect($lines)->sum('line_total');

            $discountValidator = Validator::make(
                ['discount_amount' => $validated['discount_amount'] ?? 0],
                ['discount_amount' => ['nullable', 'numeric', 'min:0', new DiscountWithinSubtotal($subtotal, (float) ($validated['discount_percent'] ?? 0))]],
                [],
                ['discount_amount' => 'Discount Amount']
            );
            if ($discountValidator->fails()) {
                DB::rollBack();

                return back()->withErrors($discountValidator)->withInput();
            }

            $calculations = app(GstCalculator::class)->calculate(
                $lines,
                (bool) ($validated['gst_applicable'] ?? false),
                (float) ($validated['discount_percent'] ?? 0),
                (float) ($validated['discount_amount'] ?? 0),
            );

            $invoice = CounterInvoice::create([
                'invoice_date' => $validated['invoice_date'],
                'customer_id' => $validated['customer_id'] ?? null,
                'walkin_name' => $validated['walkin_name'] ?? null,
                'walkin_mobile' => $validated['walkin_mobile'] ?? null,
                'payment_mode' => $validated['payment_mode'] ?? null,
                'discount_percent' => $calculations['discount_percent'],
                'discount_amount' => $calculations['discount_amount'],
                'gst_applicable' => (bool) ($validated['gst_applicable'] ?? false),
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
        $counterInvoice->load(['customer', 'items.sparePart']);

        return view('counter_invoices.edit', compact('counterInvoice'));
    }

    public function update(Request $request, CounterInvoice $counterInvoice)
    {
        $validated = $request->validate([
            'invoice_date' => 'required|date',
            'customer_id' => 'nullable|exists:customers,id|required_without_all:walkin_name,walkin_mobile',
            'walkin_name' => 'nullable|string|max:255',
            'walkin_mobile' => 'nullable|string|max:20',
            'payment_mode' => 'nullable|in:'.implode(',', array_keys(config('garage.option_lists.payment_mode'))),
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'gst_applicable' => 'boolean',
            'items' => 'required|array|min:1|max:50',
            'items.*.spare_part_id' => 'required|exists:spare_parts,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ], [
            'customer_id.required_without_all' => 'Select a customer or enter a walk-in name / mobile number.',
        ]);

        DB::beginTransaction();
        try {
            // MRP is always taken from the part master, never from the client submission
            $items = app(PartPriceResolver::class)->priceLines($validated['items']);

            // The subtotal is only known once the lines are resolved. Reject a
            // discount (percent-derived or flat amount) that would exceed it,
            // otherwise GstCalculator yields a negative grand_total.
            $lines = $this->buildGstLines($items);
            $subtotal = collect($lines)->sum('line_total');

            $discountValidator = Validator::make(
                ['discount_amount' => $validated['discount_amount'] ?? 0],
                ['discount_amount' => ['nullable', 'numeric', 'min:0', new DiscountWithinSubtotal($subtotal, (float) ($validated['discount_percent'] ?? 0))]],
                [],
                ['discount_amount' => 'Discount Amount']
            );
            if ($discountValidator->fails()) {
                DB::rollBack();

                return back()->withErrors($discountValidator)->withInput();
            }

            $calculations = app(GstCalculator::class)->calculate(
                $lines,
                (bool) ($validated['gst_applicable'] ?? false),
                (float) ($validated['discount_percent'] ?? 0),
                (float) ($validated['discount_amount'] ?? 0),
            );

            $counterInvoice->update([
                'invoice_date' => $validated['invoice_date'],
                'customer_id' => $validated['customer_id'] ?? null,
                'walkin_name' => $validated['walkin_name'] ?? null,
                'walkin_mobile' => $validated['walkin_mobile'] ?? null,
                'payment_mode' => $validated['payment_mode'] ?? null,
                'discount_percent' => $calculations['discount_percent'],
                'discount_amount' => $calculations['discount_amount'],
                'gst_applicable' => (bool) ($validated['gst_applicable'] ?? false),
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
        $this->authorize('admin');

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
