<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BulkDeletesRecords;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    use BulkDeletesRecords;

    protected function bulkDeleteModel(): string
    {
        return Employee::class;
    }

    public function index(Request $request)
    {
        $query = Employee::query();
        
        // Filter by active status if provided
        if ($request->has('active')) {
            $query->where('active', $request->active === '1');
        }
        
        $employees = $query->latest()->paginate(15)->withQueryString();
        return view('employees.index', compact('employees'));
    }

    public function create()
    {
        return view('employees.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|in:' . implode(',', array_keys(config('garage.option_lists.employee_role'))),
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'specialization' => 'nullable|in:' . implode(',', array_keys(config('garage.option_lists.employee_specialization'))),
            'active' => 'boolean',
            'join_date' => 'nullable|date',
        ]);

        // Ensure active is set (checkbox behavior)
        $validated['active'] = $request->has('active') ? true : false;

        Employee::create($validated);

        return redirect()->route('employees.index')->with('success', 'Employee added successfully.');
    }

    public function edit(Employee $employee)
    {
        return view('employees.edit', compact('employee'));
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|in:' . implode(',', array_keys(config('garage.option_lists.employee_role'))),
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'specialization' => 'nullable|in:' . implode(',', array_keys(config('garage.option_lists.employee_specialization'))),
            'active' => 'boolean',
            'join_date' => 'nullable|date',
        ]);

        // Ensure active is set (checkbox behavior)
        $validated['active'] = $request->has('active') ? true : false;

        $employee->update($validated);

        return redirect()->route('employees.index')->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return redirect()->route('employees.index')->with('success', 'Employee deleted.');
    }
}
