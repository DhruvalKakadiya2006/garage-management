<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-user-group class="w-6 h-6 text-primary" />
            Add Employee
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('employees.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <x-input name="name" label="Name" :required="true" />

                    <div class="grid grid-cols-2 gap-4">
                        <x-select name="role" label="Role" :required="true" placeholder="-- Select Role --"
                                  :options="config('garage.option_lists.employee_role')" />
                        <x-select name="specialization" label="Specialization" placeholder="-- Select Specialization --"
                                  :options="config('garage.option_lists.employee_specialization')" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="mobile" label="Mobile" :required="true" />
                        <x-input name="join_date" label="Join Date" type="date" />
                    </div>

                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="active" value="1" {{ old('active', true) ? 'checked' : '' }}
                                   class="rounded border border-gray-300 text-primary shadow-sm focus:ring-ring">
                            <span class="text-sm text-gray-700">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('employees.index')">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Save Employee
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
