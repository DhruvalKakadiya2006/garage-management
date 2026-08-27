<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-clipboard-document-list class="w-6 h-6 text-primary" />
            Edit Labour Service
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('labour-masters.update', $labourMaster) }}" method="POST" class="space-y-4"
                      x-data="{ taxable: {{ old('taxable', $labourMaster->taxable ? '1' : '0') ? 'true' : 'false' }} }">
                    @csrf
                    @method('PUT')

                    <x-input name="service_name" label="Service Name" :required="true" :value="$labourMaster->service_name"
                             placeholder="e.g., General Service, Welding, BMS Reset" />

                    <div class="grid grid-cols-2 gap-4">
                        <x-select name="category" label="Category" :required="true" placeholder="-- Select Category --"
                                  :options="config('garage.option_lists.labour_master_category')"
                                  :value="$labourMaster->category" />
                        <x-input name="standard_rate" label="Standard Rate ({{ config('garage.currency_symbol') }})" :required="true" type="number" step="0.01"
                                 :value="$labourMaster->standard_rate" placeholder="0.00" />
                    </div>

                    <x-input name="hsn_sac_code" label="HSN/SAC Code" :value="$labourMaster->hsn_sac_code" placeholder="Optional" />

                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="taxable" value="1" x-model="taxable"
                                   class="rounded border border-gray-300 text-primary shadow-sm focus:ring-ring">
                            <span class="text-sm text-gray-700">Taxable (GST applicable)</span>
                        </label>
                    </div>

                    <div x-show="taxable" x-cloak x-transition>
                        <x-input name="gst_rate" label="GST Rate (%)" type="number" step="0.01"
                                 :value="$labourMaster->gst_rate" placeholder="e.g., 18" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('inventory.index', ['tab' => 'labour'])">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Update Labour Service
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
