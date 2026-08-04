<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-users class="w-6 h-6 text-primary-600" />
            Add Customer
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('customers.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <x-input name="name" label="Name" :required="true" />

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="mobile" label="Mobile" :required="true" />
                        <x-input name="alt_mobile" label="Alt. Mobile" />
                    </div>

                    <x-input name="email" label="Email" type="email" />

                    <x-input name="address" label="Address" type="textarea" />

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="city" label="City" />
                        <x-input name="pincode" label="Pincode" />
                    </div>

                    <x-input name="gstin" label="GSTIN (optional)" />

                    <x-select name="customer_type" label="Customer Type" :required="true"
                              :options="config('garage.option_lists.customer_type')" />

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('customers.index')">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Save Customer
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
