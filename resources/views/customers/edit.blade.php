<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-users class="w-6 h-6 text-primary-600" />
            Edit Customer
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('customers.update', $customer) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <x-input name="name" label="Name" :required="true" :value="$customer->name" />

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="mobile" label="Mobile" :required="true" :value="$customer->mobile" />
                        <x-input name="alt_mobile" label="Alt. Mobile" :value="$customer->alt_mobile" />
                    </div>

                    <x-input name="email" label="Email" type="email" :value="$customer->email" />

                    <x-input name="address" label="Address" type="textarea" :value="$customer->address" />

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="city" label="City" :value="$customer->city" />
                        <x-input name="pincode" label="Pincode" :value="$customer->pincode" />
                    </div>

                    <x-input name="gstin" label="GSTIN (optional)" :value="$customer->gstin" />

                    <x-select name="customer_type" label="Customer Type" :required="true"
                              :options="config('garage.option_lists.customer_type')"
                              :value="$customer->customer_type" />

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('customers.index')">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Update Customer
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
