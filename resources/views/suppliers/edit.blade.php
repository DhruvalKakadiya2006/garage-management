<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-building-storefront class="w-6 h-6 text-primary-600" />
            Edit Supplier
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('suppliers.update', $supplier) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <x-input name="name" label="Name" :required="true" :value="$supplier->name" />

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="contact_person" label="Contact Person" :value="$supplier->contact_person" />
                        <x-input name="mobile" label="Mobile" :required="true" :value="$supplier->mobile" />
                    </div>

                    <x-input name="address" label="Address" type="textarea" rows="2" :value="$supplier->address" />

                    <x-input name="gstin" label="GSTIN" :value="$supplier->gstin" placeholder="e.g., 24ABCDE1234F1Z5" />

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('suppliers.index')">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Update Supplier
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
