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

            <!-- Filter Toggle -->
            <div class="mb-4 flex flex-wrap gap-2">
                <a href="{{ route('employees.index') }}"
                   class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors duration-150 {{ !request()->has('active') ? 'bg-primary text-white' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground' }}">
                    All
                </a>
                <a href="{{ route('employees.index', ['active' => '1']) }}"
                   class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors duration-150 {{ request('active') === '1' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground' }}">
                    Active Only
                </a>
                <a href="{{ route('employees.index', ['active' => '0']) }}"
                   class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors duration-150 {{ request('active') === '0' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground' }}">
                    Inactive Only
                </a>
            </div>

            <x-table-selection-bar
                :bulk-endpoint="route('employees.bulk-delete')"
                :bulk-export-endpoint="route('employees.bulk-export')"
                bulkConfirmMessage="Employees assigned to job cards will have their assignment cleared."
            >
                <x-slot name="single">
                    <span class="text-sm font-medium text-primary-800">
                        Selected:
                        <span x-text="selectedEmployee ? selectedEmployee.name : ''" class="font-semibold"></span>
                    </span>
                    <x-button variant="secondary" type="button"
                              @click="window.location.href = base + '/' + singleId + '/edit'">
                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                        Edit
                    </x-button>
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
                        <th class="w-10 px-6 py-3 text-left"><x-table-select-all /></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Specialization</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Mobile</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Active</th>
                    </tr>
                </x-slot>

                @foreach ($employees as $employee)
                    <tr class="hover:bg-muted/50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <x-table-checkbox :id="$employee->id" />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $employee->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 capitalize">
                            {{ $employee->specialization ? str_replace('_', ' ', $employee->specialization) : '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $employee->mobile }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
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
