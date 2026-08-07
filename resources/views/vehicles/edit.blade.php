<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-truck class="w-6 h-6 text-primary-600" />
            Edit Vehicle
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('vehicles.update', $vehicle) }}" method="POST" class="space-y-4"
                      x-data="{ isNonRto: {{ old('is_non_rto', $vehicle->is_non_rto) ? 'true' : 'false' }} }">
                    @csrf
                    @method('PUT')

                    <x-select name="customer_id" label="Customer" :required="true" placeholder="-- Select Customer --">
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" {{ old('customer_id', $vehicle->customer_id) == $customer->id ? 'selected' : '' }}>
                                {{ $customer->display_name }} — {{ $customer->mobile }}
                            </option>
                        @endforeach
                    </x-select>

                    <div class="grid grid-cols-2 gap-4">
                        <div x-show="!isNonRto" x-transition.opacity.duration.150>
                            <x-input name="registration_no" label="Registration No." :value="$vehicle->registration_no"
                                     x-bind:required="!isNonRto" x-bind:disabled="isNonRto" />
                        </div>
                        <div x-show="isNonRto" x-transition.opacity.duration.150
                             class="flex items-end pb-1">
                            <span class="text-sm text-gray-400">Registration number is not required for Non-RTO vehicles.</span>
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                <input type="checkbox" name="is_non_rto" value="1" x-model="isNonRto"
                                       {{ old('is_non_rto', $vehicle->is_non_rto) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                                Non-RTO Vehicle
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="chassis_no" label="Chassis No." :value="$vehicle->chassis_no" />
                        <x-input name="motor_battery_serial_no" label="Motor/Battery Serial No." :value="$vehicle->motor_battery_serial_no" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="vehicle_brand" label="Vehicle Brand" :value="$vehicle->vehicle_brand" />
                        <x-input name="model" label="Model" :value="$vehicle->model" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-select name="battery_type" label="Battery Type" placeholder="-- Select --"
                                  :options="config('garage.option_lists.vehicle_battery_type')"
                                  :value="$vehicle->battery_type" />
                        <x-input name="current_odometer_km" label="Current Odometer (km)" type="number"
                                 :value="$vehicle->current_odometer_km" />
                    </div>

                    <x-input name="color" label="Color" :value="$vehicle->color" />

                    <x-input name="remarks" label="Remarks" type="textarea" rows="2" :value="$vehicle->remarks" />

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('vehicles.index')">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Update Vehicle
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
