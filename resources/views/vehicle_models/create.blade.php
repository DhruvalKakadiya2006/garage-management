<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-book-open class="w-6 h-6 text-primary-600" />
            Add Model
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('vehicle-models.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <x-select name="vehicle_brand_id" label="Brand" :required="true" placeholder="-- Select Brand --">
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" {{ old('vehicle_brand_id', $selectedBrandId ?? null) == $brand->id ? 'selected' : '' }}>
                                {{ $brand->name }}
                            </option>
                        @endforeach
                    </x-select>

                    <x-input name="name" label="Model Name" :required="true" placeholder="e.g. S1 Pro, 450X" />

                    <x-select name="battery_type" label="Battery Type" placeholder="-- Select --"
                              :options="config('garage.option_lists.vehicle_battery_type')" />

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('vehicle-catalog.index')">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Save Model
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
