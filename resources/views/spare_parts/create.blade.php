<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-cube class="w-6 h-6 text-primary" />
            Add Spare Part
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('spare-parts.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="part_name" label="Part Name" :required="true" />
                        <x-input name="part_code" label="Part Code" :required="true" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="category" label="Category" placeholder="e.g., Battery, Motor, Brakes" />
                        <x-input name="compatible_vehicle_brand" label="Compatible Vehicle Brand" placeholder="e.g., Ola, Ather, All" />
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <x-select name="unit" label="Unit" :required="true" placeholder="-- Select --"
                                  :options="config('garage.option_lists.spare_part_unit')" />
                        <x-input name="purchase_price" label="Purchase Price ({{ config('garage.currency_symbol') }})" :required="true" type="number" step="0.01" />
                        <x-input name="mrp" label="MRP ({{ config('garage.currency_symbol') }})" :required="true" type="number" step="0.01" />
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <x-input name="labor_charge" label="Labor/Fitting Charge ({{ config('garage.currency_symbol') }})" type="number" step="0.01" value="0" />
                        <x-input name="hsn_code" label="HSN Code" />
                        <x-input name="gst_rate" label="GST Rate (%)" type="number" step="0.01" placeholder="e.g., 18" />
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <x-input name="current_stock_qty" label="Current Stock Qty" type="number" value="0" />
                        <x-input name="reorder_level" label="Reorder Level" type="number" value="0" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('inventory.index', ['tab' => 'parts'])">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Save Spare Part
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
