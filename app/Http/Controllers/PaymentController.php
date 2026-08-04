<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\JobCard;
use App\Models\CounterInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'payment_mode' => 'required|in:' . implode(',', array_keys(config('garage.option_lists.payment_mode'))),
            'payment_date' => 'required|date',
            'reference_no' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Load the payable model
            $payableClass = $validated['payable_type'];
            $payable = $payableClass::findOrFail($validated['payable_id']);

            // Validate that payment doesn't exceed outstanding amount
            $currentAmountPaid = $payable->amount_paid;
            $newTotal = $currentAmountPaid + $validated['amount'];
            
            if ($newTotal > $payable->grand_total) {
                DB::rollBack();
                return back()->withErrors([
                    'amount' => 'Payment amount exceeds outstanding balance. Outstanding: ' . config('garage.currency_symbol') . number_format($payable->outstanding, 2)
                ])->withInput();
            }

            // Create payment
            Payment::create($validated);

            DB::commit();

            return back()->with('success', 'Payment recorded successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to record payment: ' . $e->getMessage()])->withInput();
        }
    }
}
