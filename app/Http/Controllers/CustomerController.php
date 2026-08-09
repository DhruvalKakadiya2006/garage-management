<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::with([
            'vehicles.vehicleModel.vehicleBrand',
            'jobCards' => fn ($q) => $q->withSum('payments', 'amount'),
            'counterInvoices' => fn ($q) => $q->withSum('payments', 'amount'),
        ])
            ->withCount(['jobCards', 'counterInvoices'])
            ->latest()
            ->paginate(15);

        $customers->getCollection()->transform(function (Customer $customer) {
            $customer->setAttribute(
                'outstanding',
                $customer->jobCards->sum(fn ($jc) => (float) $jc->outstanding)
                    + $customer->counterInvoices->sum(fn ($ci) => (float) $ci->outstanding)
            );
            return $customer;
        });

        return view('customers.index', compact('customers'));
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'vehicles.vehicleModel.vehicleBrand',
            'jobCards' => fn ($q) => $q->withSum('payments', 'amount')->latest(),
            'counterInvoices' => fn ($q) => $q->withSum('payments', 'amount')->latest(),
        ]);
        $customer->loadCount(['jobCards', 'counterInvoices']);

        return view('customers.show', compact('customer'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'surname' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/', 'unique:customers,mobile'],
            'alt_mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
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
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'surname' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/', 'unique:customers,mobile,' . $customer->id],
            'alt_mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
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
            return back()->with('error', 'Cannot delete — this customer has existing job cards or invoices linked to it.');
        }
    }
}