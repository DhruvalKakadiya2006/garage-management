<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-truck class="w-6 h-6 text-primary-600" />
            Add Vehicle
        </h2>
    </x-slot>

    @php
        $brandsData = $brands->map(fn ($b) => [
            'id' => (string) $b->id,
            'name' => $b->name,
            'models' => $b->models->map(fn ($m) => [
                'id' => (string) $m->id,
                'name' => $m->name,
            ])->values(),
        ])->values();

        $oldModelId = old('vehicle_model_id');
        $oldModel = $oldModelId ? \App\Models\VehicleModel::find($oldModelId) : null;

        $initialData = [
            'customer_id' => (string) old('customer_id', $selectedCustomerId ?? ''),
            'brand_id' => $oldModel ? (string) $oldModel->vehicle_brand_id : '',
            'model_id' => $oldModel ? (string) $oldModel->id : '',
            'is_non_rto' => (bool) old('is_non_rto', false),
        ];
    @endphp

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('vehicles.store') }}" method="POST" class="space-y-4"
                      x-data="vehicleForm(@json($brandsData), @json($initialData))">
                    @csrf

                    <x-select name="customer_id" label="Customer" :required="true" placeholder="-- Select Customer --">
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" {{ old('customer_id', $selectedCustomerId ?? null) == $customer->id ? 'selected' : '' }}>
                                {{ $customer->display_name }} — {{ $customer->mobile }}
                            </option>
                        @endforeach
                    </x-select>

                    <div class="grid grid-cols-2 gap-4">
                        <div x-show="!isNonRto" x-transition.opacity.duration.150>
                            <x-input name="registration_no" label="Registration No."
                                     x-bind:required="!isNonRto" x-bind:disabled="isNonRto" />
                        </div>
                        <div x-show="isNonRto" x-transition.opacity.duration.150
                             class="flex items-end pb-1">
                            <span class="text-sm text-gray-400">Registration number is not required for Non-RTO vehicles.</span>
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                <input type="checkbox" name="is_non_rto" value="1" x-model="isNonRto"
                                       {{ old('is_non_rto') ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                                Non-RTO Vehicle
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-select name="brand_id" label="Brand" :required="true" placeholder="-- Select Brand --"
                                  x-model="selectedBrand" @change="selectedModel = ''">
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                            @endforeach
                        </x-select>

                        <x-select name="vehicle_model_id" label="Model" :required="true" placeholder="-- Select Model --"
                                  x-model="selectedModel">
                            <template x-for="m in filteredModels" :key="m.id">
                                <option :value="m.id" x-text="m.name"></option>
                            </template>
                        </x-select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="chassis_no" label="Chassis No." />
                        <x-input name="motor_battery_serial_no" label="Motor/Battery Serial No." />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="current_odometer_km" label="Current Odometer (km)" type="number" :value="0" />
                        <x-input name="color" label="Color" />
                    </div>

                    <x-input name="remarks" label="Remarks" type="textarea" rows="2" />

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('customers.index')">
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

    <script>
        function vehicleForm(brandsData, initial = {}) {
            return {
                brandsData: brandsData,
                selectedBrand: initial.brand_id || '',
                selectedModel: initial.model_id || '',
                isNonRto: !!initial.is_non_rto,

                get filteredModels() {
                    if (!this.selectedBrand) return [];
                    const brand = this.brandsData.find(b => b.id === this.selectedBrand);
                    return brand ? brand.models : [];
                },
            };
        }
    </script>
</x-app-layout>
