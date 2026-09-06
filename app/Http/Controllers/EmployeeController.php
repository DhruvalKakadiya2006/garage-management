<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AppliesSearchFilterSort;
use App\Http\Controllers\Concerns\BulkDeletesRecords;
use App\Http\Controllers\Concerns\BulkExportsRecords;
use App\Http\Requests\FilterEmployeesRequest;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    use AppliesSearchFilterSort;
    use BulkDeletesRecords;
    use BulkExportsRecords;

    protected function bulkDeleteModel(): string
    {
        return Employee::class;
    }

    protected function bulkExportEntity(): string
    {
        return 'employees';
    }

    public function index(FilterEmployeesRequest $request)
    {
        $validated = $request->validated();

        $query = Employee::query();

        // Filter by active status
        if ($request->filled('active') && $validated['active'] !== 'all') {
            if ($validated['active'] === 'active_only') {
                $query->where('active', true);
            } elseif ($validated['active'] === 'inactive_only') {
                $query->where('active', false);
            }
        }

        // Filter by role
        if ($request->filled('role') && $validated['role'] !== 'all') {
            $query->where('role', $validated['role']);
        }

        // Apply sorting
        $sortColumn = $validated['sort'] ?? 'name';
        $sortDirection = $validated['direction'] ?? 'asc';

        $query = $this->applySort(
            $query,
            $sortColumn,
            $sortDirection,
            ['name', 'role', 'active'],
            ['column' => 'name', 'direction' => 'asc']
        );

        $perPage = $this->getPerPage($request);
        $employees = $query->paginate($perPage)->withQueryString();

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
            'role' => 'required|in:'.implode(',', array_keys(config('garage.option_lists.employee_role'))),
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'specialization' => 'nullable|in:'.implode(',', array_keys(config('garage.option_lists.employee_specialization'))),
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
            'role' => 'required|in:'.implode(',', array_keys(config('garage.option_lists.employee_role'))),
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'specialization' => 'nullable|in:'.implode(',', array_keys(config('garage.option_lists.employee_specialization'))),
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
        $this->authorize('admin');

        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Employee deleted.');
    }
}
