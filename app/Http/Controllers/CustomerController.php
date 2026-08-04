<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::latest()->paginate(15);
        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:20|unique:customers,mobile',
            'alt_mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'gstin' => 'nullable|string|max:20',
            'customer_type' => 'required|in:' . implode(',', array_keys(config('garage.option_lists.customer_type'))),
        ]);

        Customer::create($validated);

        return redirect()->route('customers.index')->with('success', 'Customer added successfully.');
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:20|unique:customers,mobile,' . $customer->id,
            'alt_mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'gstin' => 'nullable|string|max:20',
            'customer_type' => 'required|in:' . implode(',', array_keys(config('garage.option_lists.customer_type'))),
        ]);

        $customer->update($validated);

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        try {
            $customer->delete();
            return redirect()->route('customers.index')->with('success', 'Customer deleted.');
        } catch (QueryException $e) {
            return back()->with('error', 'Cannot delete — this customer has existing job cards, estimates, or invoices linked to it.');
        }
    }
}