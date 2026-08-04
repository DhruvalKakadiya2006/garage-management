<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-truck class="w-6 h-6 text-primary-600" />
            Add Vehicle
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('vehicles.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <x-select name="customer_id" label="Customer" :required="true" placeholder="-- Select Customer --">
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                {{ $customer->name }} — {{ $customer->mobile }}
                            </option>
                        @endforeach
                    </x-select>

                    <x-input name="registration_no" label="Registration No." :required="true" />

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="chassis_no" label="Chassis No." />
                        <x-input name="motor_battery_serial_no" label="Motor/Battery Serial No." />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="vehicle_brand" label="Vehicle Brand" />
                        <x-input name="model" label="Model" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-select name="battery_type" label="Battery Type" placeholder="-- Select --"
                                  :options="config('garage.option_lists.vehicle_battery_type')" />
                        <x-input name="battery_capacity" label="Battery Capacity (kWh)" type="number" step="0.01" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="purchase_date" label="Purchase Date" type="date" />
                        <x-input name="warranty_valid_till" label="Warranty Valid Till" type="date" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="current_odometer_km" label="Current Odometer (km)" type="number" :value="0" />
                        <x-input name="color" label="Color" />
                    </div>

                    <x-input name="remarks" label="Remarks" type="textarea" rows="2" />

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('vehicles.index')">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Save Vehicle
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
