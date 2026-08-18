<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-user-group class="w-6 h-6 text-primary-600" />
                Employees
            </h2>
            <x-button :href="route('employees.create')">
                <x-heroicon-o-plus class="w-4 h-4 mr-1.5" />
                Add Employee
            </x-button>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Filter Toggle -->
            <div class="mb-4 flex flex-wrap gap-2">
                <a href="{{ route('employees.index') }}"
                   class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors duration-150 {{ !request()->has('active') ? 'bg-primary-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                    All
                </a>
                <a href="{{ route('employees.index', ['active' => '1']) }}"
                   class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors duration-150 {{ request('active') === '1' ? 'bg-primary-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                    Active Only
                </a>
                <a href="{{ route('employees.index', ['active' => '0']) }}"
                   class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors duration-150 {{ request('active') === '0' ? 'bg-primary-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                    Inactive Only
                </a>
            </div>

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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Specialization</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mobile</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Active</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </x-slot>

                @foreach ($employees as $employee)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-xs font-medium text-gray-900">
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
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600 capitalize">
                            {{ $employee->specialization ? str_replace('_', ' ', $employee->specialization) : '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">
                            {{ $employee->mobile }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if ($employee->active)
                                <x-badge color="green">
                                    <x-heroicon-o-check-circle class="w-3.5 h-3.5 mr-1" />
                                    Active
                                </x-badge>
                            @else
                                <x-badge color="gray">
                                    <x-heroicon-o-x-circle class="w-3.5 h-3.5 mr-1" />
                                    Inactive
                                </x-badge>
                            @endif
                        </td>
                        <x-data-table-actions
                            :model="$employee"
                            :editRoute="fn ($e) => route('employees.edit', $e)"
                            :deleteRoute="fn ($e) => route('employees.destroy', $e)"
                            confirm="Delete this employee?"
                        />
                    </tr>
                @endforeach
            </x-data-table>

        </div>
    </div>
</x-app-layout>
