<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-cube class="w-6 h-6 text-primary" />
            Edit Spare Part
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('spare-parts.update', $sparePart) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="part_name" label="Part Name" :required="true" :value="$sparePart->part_name" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-select name="category" label="Category" :required="true" placeholder="-- Select --"
                                  :options="[
                                    'accessories' => 'Accessories',
                                    'battery-and-bms' => 'Battery & BMS',
                                    'brake-system' => 'Brake System',
                                    'brakes' => 'Brakes',
                                    'cables-and-wiring' => 'Cables & Wiring',
                                    'charger-and-charging' => 'Charger & Charging',
                                    'drive-system' => 'Drive System',
                                    'electrical' => 'Electrical',
                                    'fasteners-and-hardware' => 'Fasteners & Hardware',
                                    'instruments-and-meters' => 'Instruments & Meters',
                                    'lighting-and-horns' => 'Lighting & Horns',
                                    'motor-and-motor-parts' => 'Motor & Motor Parts',
                                    'suspension-and-steering' => 'Suspension & Steering',
                                    'switches-and-controls' => 'Switches & Controls',
                                    'wheels-tyres-and-bearings' => 'Wheels, Tyres & Bearings',
                                  ]" :value="$sparePart->category" />
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <x-select name="unit" label="Unit" :required="true" placeholder="-- Select --" :value="$sparePart->unit"
                              :options="config('garage.option_lists.spare_part_unit')" />
                        <x-input name="purchase_price" label="Purchase Price ({{ config('garage.currency_symbol') }})" :required="true" type="number" step="0.01" :value="$sparePart->purchase_price" />
                        <x-input name="mrp" label="MRP ({{ config('garage.currency_symbol') }})" :required="true" type="number" step="0.01" :value="$sparePart->mrp" />
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <x-input name="labor_charge" label="Labor/Fitting Charge ({{ config('garage.currency_symbol') }})" type="number" step="0.01" :value="$sparePart->labor_charge" />
                        <x-input name="hsn_code" label="HSN Code" :value="$sparePart->hsn_code" />
                        <x-input name="gst_rate" label="GST Rate (%)" type="number" step="0.01" :value="$sparePart->gst_rate" placeholder="e.g., 18" />
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <x-input name="reorder_level" label="Reorder Level" type="number" :value="$sparePart->reorder_level" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('inventory.index', ['tab' => 'parts'])">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Update Spare Part
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>