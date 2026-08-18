<?php

namespace App\Http\Controllers;

use App\Models\CounterInvoice;
use App\Models\JobCard;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Display a listing of all payments (read-only view).
     */
    public function index()
    {
        $payments = Payment::with('payable.customer')
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

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
            // Load the payable model
            $payableClass = $validated['payable_type'];
            $payable = $payableClass::findOrFail($validated['payable_id']);

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

            DB::commit();

            return back()->with('success', 'Payment recorded successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            return back()->withErrors(['error' => 'Something went wrong saving this record. Please try again.'])->withInput();
        }
    }

    /**
     * Show the form for editing a payment record.
     */
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
