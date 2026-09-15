<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AppliesSearchFilterSort;
use App\Http\Controllers\Concerns\BulkDeletesRecords;
use App\Http\Controllers\Concerns\BulkExportsRecords;
use App\Http\Requests\FilterPaymentsRequest;
use App\Models\CounterInvoice;
use App\Models\JobCard;
use App\Models\Payment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use AppliesSearchFilterSort;
    use BulkDeletesRecords;
    use BulkExportsRecords;

    protected function bulkDeleteModel(): string
    {
        return Payment::class;
    }

    protected function bulkExportEntity(): string
    {
        return 'payments';
    }

    /**
     * Display a listing of all payments (read-only view).
     */
    public function index(FilterPaymentsRequest $request)
    {
        $validated = $request->validated();

        $query = Payment::with('payable.customer');

        // Filter by payment mode
        if ($request->filled('payment_mode') && $validated['payment_mode'] !== 'all') {
            $query->where('payment_mode', $validated['payment_mode']);
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('payment_date', '>=', $validated['from_date']);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('payment_date', '<=', $validated['to_date']);
        }

        // Filter by document type
        if ($request->filled('document_type') && $validated['document_type'] !== 'all') {
            if ($validated['document_type'] === 'job_card') {
                $query->where('payable_type', JobCard::class);
            } elseif ($validated['document_type'] === 'counter_invoice') {
                $query->where('payable_type', CounterInvoice::class);
            }
        }

        // Apply sorting
        $sortColumn = $validated['sort'] ?? 'payment_date';
        $sortDirection = $validated['direction'] ?? 'desc';

        $query = $this->applySort(
            $query,
            $sortColumn,
            $sortDirection,
            ['payment_date', 'amount', 'payment_mode'],
            ['column' => 'payment_date', 'direction' => 'desc']
        );

        $perPage = $this->getPerPage($request);
        $payments = $query->paginate($perPage)->withQueryString();

        return view('payments.index', compact('payments'));
    }

    /**
     * Store a newly created payment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'payable_type' => 'required|in:App\Models\JobCard,App\Models\CounterInvoice',
            'payable_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
            'payment_mode' => 'required|in:'.implode(',', array_keys(config('garage.option_lists.payment_mode'))),
            'payment_date' => 'required|date',
            'reference_no' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Load the payable model. lockForUpdate serializes concurrent
            // payment writers against the same document on row-locking
            // databases; on SQLite (single writer) it is a no-op and the
            // post-insert guard below provides the backstop.
            $payableClass = $validated['payable_type'];
            $payable = $payableClass::whereKey($validated['payable_id'])->lockForUpdate()->firstOrFail();

            // Explicitly confirm the resolved record is one of the two payable
            // documents. Note: the client submits payable_type + payable_id
            // directly and there is intentionally no further ownership scoping
            // (no "does this user own this record" check) — this is a deliberate,
            // permissive design for a single-trusted-user application, so complex
            // ownership logic does not apply here.
            if (! $payable instanceof JobCard && ! $payable instanceof CounterInvoice) {
                DB::rollBack();

                return back()->withErrors(['payable_type' => 'Invalid payment target.'])->withInput();
            }

            // Payments can only be recorded once a document is billed
            if ($payable instanceof JobCard && ! $payable->is_billed) {
                DB::rollBack();

                return back()->withErrors([
                    'amount' => 'This job card has not been billed yet. Add parts / labour before recording a payment.',
                ])->withInput();
            }

            // Validate that payment doesn't exceed outstanding amount
            $currentAmountPaid = $payable->amount_paid;
            $newTotal = $currentAmountPaid + $validated['amount'];

            if ($newTotal > $payable->grand_total) {
                DB::rollBack();

                return back()->withErrors([
                    'amount' => 'Payment amount exceeds outstanding balance. Outstanding: '.config('garage.currency_symbol').number_format($payable->outstanding, 2),
                ])->withInput();
            }

            // Create payment
            Payment::create($validated);

            // Post-insert invariant: recompute the live paid total inside the
            // transaction so two requests that both passed the pre-check above
            // can never jointly push the document past its grand total.
            if (round((float) $payable->payments()->sum('amount'), 2) > round((float) $payable->grand_total, 2)) {
                throw new \RuntimeException('Concurrent writes exceeded the payable grand total.');
            }

            DB::commit();

            return back()->with('success', 'Payment recorded successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            if ($e instanceof ModelNotFoundException) {
                return $this->invalidPayableResponse();
            }

            Log::error($e);

            return back()->withErrors(['error' => 'Something went wrong saving this record. Please try again.'])->withInput();
        }
    }

    /**
     * A payable id that no longer exists (or never did) is a user-input
     * problem, not an application fault — surface it as a field error instead
     * of the generic failure message. Caller has already rolled back the
     * transaction if one was active.
     */
    protected function invalidPayableResponse(): RedirectResponse
    {
        return back()
            ->withErrors(['payable_id' => 'The selected document could not be found. It may have been deleted.'])
            ->withInput();
    }

    public function edit(Payment $payment)
    {
        $payment->load('payable');

        return view('payments.edit', compact('payment'));
    }

    /**
     * Update a payment record. The linked Job Card / Counter Invoice's derived
     * outstanding balance recalculates automatically since it is computed live
     * from the payments sum.
     */
    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_mode' => 'required|in:'.implode(',', array_keys(config('garage.option_lists.payment_mode'))),
            'payment_date' => 'required|date',
            'reference_no' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $payable = $payment->payable;

            if ($payable && ($payable instanceof JobCard || $payable instanceof CounterInvoice)) {
                // Re-fetch under a row lock so concurrent payment writers
                // targeting the same document serialize their balance checks
                // (no-op on SQLite; the post-update guard below backstops it).
                $payableClass = get_class($payable);
                $payable = $payableClass::whereKey($payable->getKey())->lockForUpdate()->firstOrFail();

                // Other payments on the document + the corrected amount must
                // never exceed its grand total.
                $otherPaymentsTotal = $payable->amount_paid - $payment->amount;
                $newTotal = $otherPaymentsTotal + $validated['amount'];

                if ($newTotal > $payable->grand_total) {
                    DB::rollBack();

                    return back()->withErrors([
                        'amount' => 'Payment amount exceeds outstanding balance. Outstanding: '.config('garage.currency_symbol').number_format($payable->outstanding, 2),
                    ])->withInput();
                }
            }

            $payment->update($validated);

            // Post-update invariant mirroring store(): the live paid total can
            // never exceed the grand total, even under concurrent writes.
            // When payable was deleted, there is no document to guard.
            if ($payable && round((float) $payable->payments()->sum('amount'), 2) > round((float) $payable->grand_total, 2)) {
                throw new \RuntimeException('Concurrent writes exceeded the payable grand total.');
            }

            DB::commit();

            return redirect()->route('payments.index')->with('success', 'Payment updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            return back()->withErrors(['error' => 'Something went wrong saving this record. Please try again.'])->withInput();
        }
    }

    /**
     * Remove (void) a payment record only. The linked Job Card / Counter
     * Invoice is never touched — its derived outstanding balance simply
     * recalculates from the remaining payments.
     */
    public function destroy(Payment $payment)
    {
        try {
            $payment->delete();

            return redirect()->route('payments.index')->with('success', 'Payment record deleted.');
        } catch (\Exception $e) {
            Log::error($e);

            return back()->withErrors(['error' => 'Something went wrong deleting this record. Please try again.']);
        }
    }
}
