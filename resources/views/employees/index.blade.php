<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-user-group class="w-6 h-6 text-primary" />
                Employees
            </h2>
            <x-button :href="route('employees.create')">
                <x-heroicon-o-plus class="w-4 h-4" />
                Add Employee
            </x-button>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full sm:px-6 lg:px-8"
             x-data="mergeAlpineData(tableSelection({ bulkEndpoint: '{{ route('employees.bulk-delete') }}', bulkExportEndpoint: '{{ route('employees.bulk-export') }}' }), employeeList())">

            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Search and Filter Form --}}
            <form method="GET" action="{{ route('employees.index') }}" class="mb-6">
                <div class="space-y-4">
                    {{-- Filters --}}
                    <x-filter-panel>
                        <div>
                            <label for="active" class="block text-sm font-medium text-gray-700 mb-1">Active Status</label>
                            <select name="active" id="active" class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="all" {{ request('active', 'all') === 'all' ? 'selected' : '' }}>All Employees</option>
                                <option value="active_only" {{ request('active') === 'active_only' ? 'selected' : '' }}>Active Only</option>
                                <option value="inactive_only" {{ request('active') === 'inactive_only' ? 'selected' : '' }}>Inactive Only</option>
                            </select>
                        </div>

                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select name="role" id="role" class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="all" {{ request('role', 'all') === 'all' ? 'selected' : '' }}>All Roles</option>
                                @foreach(config('garage.option_lists.employee_role') as $value => $label)
                                    <option value="{{ $value }}" {{ request('role') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="per_page" class="block text-sm font-medium text-gray-700 mb-1">Items Per Page</label>
                            <select name="per_page" id="per_page" class="w-full border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="15" {{ request('per_page', 15) == 15 ? 'selected' : '' }}>15</option>
                                <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <x-button type="submit" class="w-full">
                                <x-heroicon-o-funnel class="w-4 h-4" />
                                Apply Filters
                            </x-button>
                        </div>

                        @if(request()->hasAny(['active', 'role', 'sort']))
                            <div class="flex items-end">
                                <x-button variant="secondary" type="button" onclick="window.location.href='{{ route('employees.index') }}'" class="w-full">
                                    Clear
                                </x-button>
                            </div>
                        @endif
                    </x-filter-panel>

                    {{-- Active Filters --}}
                    <x-active-filters-bar 
                        :filters="[
                            'active' => request('active') !== 'all' ? request('active') : null,
                            'role' => request('role') !== 'all' ? request('role') : null,
                        ]"
                        :baseRoute="'employees.index'"
                    />
                </div>
            </form>

            <x-table-selection-bar
                :bulk-endpoint="route('employees.bulk-delete')"
                :bulk-export-endpoint="route('employees.bulk-export')"
                bulkConfirmMessage="Employees assigned to job cards will have their assignment cleared."
            >
<x-slot name="single">
                    <x-button type="button" @click="$dispatch('open-modal', 'bulk-delete-confirm')" class="flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                        <x-heroicon-o-trash class="w-4 h-4" />
                        Delete
                    </x-button>
                    <x-bulk-delete-confirm
                        :name="'bulk-delete-confirm'"
                        :title="'Delete this employee?'"
                        :confirm-label="'Delete'"
                        :message="'Employees assigned to job cards will have their assignment cleared.'"
                    />
                </x-slot>
            </x-table-selection-bar>

            <x-data-table
                :empty="$employees->isEmpty()"
                emptyMessage='No employees yet. Click "Add Employee" to create one.'
                emptyIcon="user-group"
                :emptyHref="route('employees.create')"
                emptyLabel="Add Employee"
                :paginator="$employees"
            >
                <x-slot name="header">
                    <tr>
                        <th class="align-middle w-10 px-6 py-3 text-left"><x-table-select-all /></th>
                        <x-sortable-header 
                            column="name" 
                            label="Name" 
                            :currentSort="request('sort')" 
                            :currentDirection="request('direction', 'asc')"
                        />
                        <x-sortable-header 
                            column="role" 
                            label="Role" 
                            :currentSort="request('sort')" 
                            :currentDirection="request('direction', 'asc')"
                        />
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Specialization</th>
                        <th class="align-middle px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Mobile</th>
                        <x-sortable-header 
                            column="active" 
                            label="Active" 
                            :currentSort="request('sort')" 
                            :currentDirection="request('direction', 'asc')"
                        />
                    </tr>
                </x-slot>

                @foreach ($employees as $employee)
                    <tr class="hover:bg-muted/50">
                        <td class="align-middle px-6 py-2 whitespace-nowrap">
                            <x-table-checkbox :id="$employee->id" />
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $employee->name }}
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap">
                            <x-badge :color="match ($employee->role) {
                                'technician' => 'primary',
                                'supervisor' => 'purple',
                                'front_desk' => 'purple',
                                'admin' => 'red',
                                default => 'gray',
                            }">
                                {{ ucwords(str_replace('_', ' ', $employee->role)) }}
                            </x-badge>
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-600 capitalize">
                            {{ $employee->specialization ? str_replace('_', ' ', $employee->specialization) : '-' }}
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap text-sm text-gray-600">
                            {{ $employee->mobile }}
                        </td>
                        <td class="align-middle px-6 py-2 whitespace-nowrap">
                            @if ($employee->active)
                                <x-badge color="green">
                                    <x-heroicon-o-check-circle class="w-3.5 h-3.5" />
                                    Active
                                </x-badge>
                            @else
                                <x-badge color="gray">
                                    <x-heroicon-o-x-circle class="w-3.5 h-3.5" />
                                    Inactive
                                </x-badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-data-table>

        </div>
    </div>

    <script>
        function employeeList() {
            const employees = @json($employees->getCollection()->map(fn ($e) => [
                'id' => (string) $e->id,
                'name' => $e->name,
            ])->values());
            const base = '{{ url('employees') }}';

            return {
                base: base,
                employees: employees,

                get selectedEmployee() {
                    return this.employees.find(e => e.id === this.singleId) || null;
                },
            };
        }
    </script>
</x-app-layout>
