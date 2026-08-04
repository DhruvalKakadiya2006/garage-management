<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-building-storefront class="w-6 h-6 text-primary-600" />
            Add Supplier
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('suppliers.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <x-input name="name" label="Name" :required="true" />

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="contact_person" label="Contact Person" />
                        <x-input name="mobile" label="Mobile" :required="true" />
                    </div>

                    <x-input name="address" label="Address" type="textarea" rows="2" />

                    <x-input name="gstin" label="GSTIN" placeholder="e.g., 24ABCDE1234F1Z5" />

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('suppliers.index')">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Save Supplier
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
