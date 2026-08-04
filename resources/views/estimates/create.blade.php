<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-document-text class="w-6 h-6 text-primary-600" />
            Create New Estimate
        </h2>
    </x-slot>

    @php
        $vehicleData = $vehicles->map(fn($v) => [
            'id' => $v->id,
            'customer_id' => $v->customer_id,
            'registration_no' => $v->registration_no,
            'brand' => $v->vehicle_brand,
            'model' => $v->model,
        ])->values();
    @endphp

    <div class="py-6" x-data="estimateForm()">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <form action="{{ route('estimates.store') }}" method="POST" class="space-y-6">
                    @csrf

                    <!-- Header Fields -->
                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="estimate_date" label="Estimate Date" :required="true" type="date" :value="date('Y-m-d')" />
                        <x-input name="valid_until" label="Valid Until" type="date" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Customer *</label>
                            <select name="customer_id" x-model="selectedCustomer" @change="filterVehicles()"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                                <option value="">-- Select Customer --</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }} — {{ $customer->mobile }}</option>
                                @endforeach
                            </select>
                            @error('customer_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vehicle *</label>
                            <select name="vehicle_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                                <option value="">-- Select Vehicle --</option>
                                <template x-for="vehicle in filteredVehicles" :key="vehicle.id">
                                    <option :value="vehicle.id" x-text="`${vehicle.registration_no} - ${vehicle.brand} ${vehicle.model}`"></option>
                                </template>
                            </select>
                            @error('vehicle_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <x-select name="status" label="Status" :required="true" value="draft"
                                  :options="config('garage.option_lists.estimate_status')" />
                    </div>

                    <!-- Parts Section -->
                    <div class="border-t pt-4">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="font-semibold text-gray-900">Parts</h3>
                            <button type="button" @click="addPart()" class="text-sm text-primary-600 hover:text-primary-800">+ Add Part</button>
                        </div>
                        <template x-for="(part, index) in parts" :key="index">
                            <div class="grid grid-cols-12 gap-2 mb-2 items-end">
                                <div class="col-span-4">
                                    <select :name="`parts[${index}][spare_part_id]`" @change="autofillPart(index, $event)"
                                            class="block w-full text-sm rounded-md border-gray-300 shadow-sm" required>
                                        <option value="">-- Select Part --</option>
                                        @foreach ($spareParts as $sp)
                                            <option value="{{ $sp->id }}" data-mrp="{{ $sp->mrp }}" data-labor="{{ $sp->labor_charge }}" data-gst="{{ $sp->gst_rate }}">
                                                {{ $sp->part_name }} ({{ $sp->part_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <input type="number" :name="`parts[${index}][quantity]`" x-model="part.quantity" @input="calculatePartLine(index)"
                                           placeholder="Qty" class="block w-full text-sm rounded-md border-gray-300 shadow-sm" required min="1">
                                </div>
                                <div class="col-span-2">
                                    <input type="number" step="0.01" :name="`parts[${index}][mrp]`" x-model="part.mrp" @input="calculatePartLine(index)"
                                           placeholder="MRP" class="block w-full text-sm rounded-md border-gray-300 shadow-sm" required min="0">
                                </div>
                                <div class="col-span-2">
                                    <input type="number" step="0.01" :name="`parts[${index}][labor_charge]`" x-model="part.labor_charge" @input="calculatePartLine(index)"
                                           placeholder="Labor" class="block w-full text-sm rounded-md border-gray-300 shadow-sm" required min="0">
                                </div>
                                <div class="col-span-1 text-right text-sm font-medium" x-text="'{{ config('garage.currency_symbol') }}' + part.lineTotal.toFixed(2)"></div>
                                <div class="col-span-1">
                                    <button type="button" @click="removePart(index)" title="Remove"
                                            class="inline-flex items-center justify-center rounded-md p-1.5 text-red-600 transition-colors duration-150 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
                                        <x-heroicon-o-x-mark class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Labours Section -->
                    <div class="border-t pt-4">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="font-semibold text-gray-900">Labour</h3>
                            <button type="button" @click="addLabour()" class="text-sm text-primary-600 hover:text-primary-800">+ Add Labour</button>
                        </div>
                        <template x-for="(labour, index) in labours" :key="index">
                            <div class="grid grid-cols-12 gap-2 mb-2 items-end">
                                <div class="col-span-6">
                                    <select :name="`labours[${index}][labour_master_id]`" @change="autofillLabour(index, $event)"
                                            class="block w-full text-sm rounded-md border-gray-300 shadow-sm" required>
                                        <option value="">-- Select Service --</option>
                                        @foreach ($labourMasters as $lm)
                                            <option value="{{ $lm->id }}" data-rate="{{ $lm->standard_rate }}" data-taxable="{{ $lm->taxable ? '1' : '0' }}" data-gst="{{ $lm->gst_rate }}">
                                                {{ $lm->service_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <input type="number" step="0.01" :name="`labours[${index}][qty]`" x-model="labour.qty" @input="calculateLabourLine(index)"
                                           placeholder="Qty" class="block w-full text-sm rounded-md border-gray-300 shadow-sm" required min="0">
                                </div>
                                <div class="col-span-2">
                                    <input type="number" step="0.01" :name="`labours[${index}][rate]`" x-model="labour.rate" @input="calculateLabourLine(index)"
                                           placeholder="Rate" class="block w-full text-sm rounded-md border-gray-300 shadow-sm" required min="0">
                                </div>
                                <div class="col-span-1 text-right text-sm font-medium" x-text="'{{ config('garage.currency_symbol') }}' + labour.lineTotal.toFixed(2)"></div>
                                <div class="col-span-1">
                                    <button type="button" @click="removeLabour(index)" title="Remove"
                                            class="inline-flex items-center justify-center rounded-md p-1.5 text-red-600 transition-colors duration-150 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
                                        <x-heroicon-o-x-mark class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Totals & GST -->
                    <div class="border-t pt-4">
                        <div class="max-w-md ml-auto space-y-3">
                            <div class="flex justify-between text-sm"><span>Parts Subtotal:</span><span x-text="'{{ config('garage.currency_symbol') }}' + partsSubtotal.toFixed(2)"></span></div>
                            <div class="flex justify-between text-sm"><span>Labour Subtotal:</span><span x-text="'{{ config('garage.currency_symbol') }}' + laboursSubtotal.toFixed(2)"></span></div>
                            <div class="flex justify-between text-sm font-medium"><span>Subtotal:</span><span x-text="'{{ config('garage.currency_symbol') }}' + subtotal.toFixed(2)"></span></div>
                            
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" step="0.01" name="discount_percent" x-model="discountPercent" @input="calculateTotals()"
                                       placeholder="Discount %" class="text-sm rounded-md border-gray-300 shadow-sm" min="0" max="100">
                                <input type="number" step="0.01" name="discount_amount" x-model="discountAmount" @input="discountPercent=0; calculateTotals()"
                                       placeholder="Discount {{ config('garage.currency_symbol') }}" class="text-sm rounded-md border-gray-300 shadow-sm" min="0">
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="gst_applicable" x-model="gstApplicable" @change="calculateTotals()"
                                       class="rounded border-gray-300 text-primary-600 shadow-sm">
                                <span class="ml-2 text-sm">GST Applicable</span>
                            </div>
                            
                            <div class="flex justify-between text-sm"><span>Round Off:</span><span x-text="(roundOffAmount > 0 ? '+' : '') + '{{ config('garage.currency_symbol') }}' + roundOffAmount.toFixed(2)"></span></div>
                            <div class="flex justify-between text-lg font-bold border-t pt-2"><span>Grand Total:</span><span x-text="'{{ config('garage.currency_symbol') }}' + grandTotal.toFixed(2)"></span></div>
                        </div>
                    </div>

                    <div>
                        <x-input name="notes" label="Notes" type="textarea" rows="3" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('estimates.index')">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Save Estimate
                        </x-button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script>
        function estimateForm() {
            return {
                selectedCustomer: '',
                allVehicles: @json($vehicleData),
                filteredVehicles: [],
                parts: [],
                labours: [],
                partsSubtotal: 0,
                laboursSubtotal: 0,
                subtotal: 0,
                discountPercent: 0,
                discountAmount: 0,
                gstApplicable: false,
                grandTotal: 0,
                roundOffAmount: 0,
                
                filterVehicles() {
                    this.filteredVehicles = this.allVehicles.filter(v => v.customer_id == this.selectedCustomer);
                },
                
                addPart() {
                    this.parts.push({quantity: 1, mrp: 0, labor_charge: 0, gstRate: 0, lineTotal: 0});
                },
                
                removePart(index) {
                    this.parts.splice(index, 1);
                    this.calculateTotals();
                },
                
                autofillPart(index, event) {
                    const option = event.target.selectedOptions[0];
                    this.parts[index].mrp = parseFloat(option.dataset.mrp || 0);
                    this.parts[index].labor_charge = parseFloat(option.dataset.labor || 0);
                    this.parts[index].gstRate = parseFloat(option.dataset.gst || 0);
                    this.calculatePartLine(index);
                },
                
                calculatePartLine(index) {
                    const part = this.parts[index];
                    part.lineTotal = (parseFloat(part.quantity) || 0) * ((parseFloat(part.mrp) || 0) + (parseFloat(part.labor_charge) || 0));
                    this.calculateTotals();
                },
                
                addLabour() {
                    this.labours.push({qty: 1, rate: 0, gstRate: 0, lineTotal: 0});
                },
                
                removeLabour(index) {
                    this.labours.splice(index, 1);
                    this.calculateTotals();
                },
                
                autofillLabour(index, event) {
                    const option = event.target.selectedOptions[0];
                    this.labours[index].rate = parseFloat(option.dataset.rate || 0);
                    this.labours[index].gstRate = (option.dataset.taxable === '1') ? parseFloat(option.dataset.gst || 0) : 0;
                    this.calculateLabourLine(index);
                },
                
                calculateLabourLine(index) {
                    const labour = this.labours[index];
                    labour.lineTotal = (parseFloat(labour.qty) || 0) * (parseFloat(labour.rate) || 0);
                    this.calculateTotals();
                },
                
                calculateTotals() {
                    this.partsSubtotal = this.parts.reduce((sum, p) => sum + (parseFloat(p.lineTotal) || 0), 0);
                    this.laboursSubtotal = this.labours.reduce((sum, l) => sum + (parseFloat(l.lineTotal) || 0), 0);
                    this.subtotal = this.partsSubtotal + this.laboursSubtotal;
                    
                    if (this.discountPercent > 0) {
                        this.discountAmount = (this.subtotal * this.discountPercent) / 100;
                    }
                    
                    const discountAmount = parseFloat(this.discountAmount) || 0;
                    const discountRatio = this.subtotal > 0 ? discountAmount / this.subtotal : 0;
                    let cgstAmount = 0;
                    let sgstAmount = 0;
                    
                    if (this.gstApplicable) {
                        [this.parts, this.labours].forEach(lines => {
                            lines.forEach(l => {
                                const rate = parseFloat(l.gstRate) || 0;
                                if (rate > 0) {
                                    const tax = (l.lineTotal * (1 - discountRatio) * rate) / 100;
                                    const half = Math.round(tax / 2 * 100) / 100;
                                    cgstAmount += half;
                                    sgstAmount += half;
                                }
                            });
                        });
                    }
                    
                    const afterDiscount = this.subtotal - discountAmount;
                    const totalBeforeRounding = afterDiscount + cgstAmount + sgstAmount;
                    const roundedTotal = Math.round(totalBeforeRounding);
                    this.roundOffAmount = Math.round((roundedTotal - totalBeforeRounding) * 100) / 100;
                    this.grandTotal = roundedTotal;
                }
            }
        }
    </script>
</x-app-layout>
