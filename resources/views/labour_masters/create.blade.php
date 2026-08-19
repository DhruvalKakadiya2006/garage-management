<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-clipboard-document-list class="w-6 h-6 text-primary" />
            Add Labour Service
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('labour-masters.store') }}" method="POST" class="space-y-4" x-data="{ taxable: {{ old('taxable', '1') ? 'true' : 'false' }} }">
                    @csrf

                    <x-input name="service_name" label="Service Name" :required="true"
                             placeholder="e.g., General Service, Welding, BMS Reset" />

                    <div class="grid grid-cols-2 gap-4">
                        <x-select name="category" label="Category" :required="true" placeholder="-- Select Category --"
                                  :options="config('garage.option_lists.labour_master_category')" />
                        <x-input name="standard_rate" label="Standard Rate ({{ config('garage.currency_symbol') }})" :required="true" type="number" step="0.01" placeholder="0.00" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="estimated_time_minutes" label="Estimated Time (minutes)" type="number"
                                 placeholder="e.g., 30, 60, 120" />
                        <x-input name="hsn_sac_code" label="HSN/SAC Code" placeholder="Optional" />
                    </div>

                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="taxable" value="1" x-model="taxable"
                                   class="rounded border-input text-primary shadow-sm focus:ring-ring">
                            <span class="text-sm text-gray-700">Taxable (GST applicable)</span>
                        </label>
                    </div>

                    <div x-show="taxable" x-cloak x-transition>
                        <x-input name="gst_rate" label="GST Rate (%)" type="number" step="0.01" placeholder="e.g., 18" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('labour-masters.index')">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Save Labour Service
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
