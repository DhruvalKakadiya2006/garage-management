<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-clipboard-document-list class="w-6 h-6 text-primary-600" />
                Assign & Bill — {{ $jobCard->job_card_no }}
            </h2>
            <x-button variant="secondary" :href="route('job-cards.show', $jobCard)">
                Back
            </x-button>
        </div>
    </x-slot>

    @php
        $initialData = [
            'assigned_technician_id' => $jobCard->assigned_technician_id ? (int) $jobCard->assigned_technician_id : '',
            'supervisor_id' => $jobCard->supervisor_id ? (int) $jobCard->supervisor_id : '',
            'discount_percent' => (float) $jobCard->discount_percent,
            'discount_amount' => (float) $jobCard->discount_amount,
            'gst_applicable' => (bool) $jobCard->gst_applicable,
            'parts' => $jobCard->parts->map(fn($p) => [
                'spare_part_id' => (int) $p->spare_part_id,
                'quantity' => (int) $p->quantity,
                'mrp' => (float) ($spareParts->firstWhere('id', $p->spare_part_id)?->mrp ?? $p->mrp),
                'labor_charge' => (float) $p->labor_charge,
                'under_warranty' => (bool) $p->under_warranty,
                'gstRate' => (float) ($spareParts->firstWhere('id', $p->spare_part_id)?->gst_rate ?? 0),
                'lineTotal' => (float) $p->line_total,
            ])->values(),
            'labours' => $jobCard->labours->map(fn($l) => [
                'labour_master_id' => (int) $l->labour_master_id,
                'technician_id' => $l->technician_id ? (int) $l->technician_id : '',
                'qty' => (float) $l->qty,
                'rate' => (float) $l->rate,
                'gstRate' => (float) ($labourMasters->firstWhere('id', $l->labour_master_id)?->taxable ? ($labourMasters->firstWhere('id', $l->labour_master_id)?->gst_rate ?? 0) : 0),
                'lineTotal' => (float) $l->line_total,
            ])->values(),
        ];
    @endphp

    <div class="py-6" x-data="assignForm()">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="space-y-6">

                @if($errors->any())
                    <div class="bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-md">
                        <ul class="list-disc list-inside text-sm">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Read-only job summary -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="grid grid-cols-3 gap-6 text-sm">
                        <div>
                            <div class="font-medium text-gray-500 text-xs uppercase tracking-wider mb-1">Customer</div>
                            <div class="font-semibold text-gray-900">{{ $jobCard->customer->display_name }}</div>
                            <div class="text-gray-600">{{ $jobCard->customer->mobile }}</div>
                        </div>
                        <div>
                            <div class="font-medium text-gray-500 text-xs uppercase tracking-wider mb-1">Vehicle</div>
                            <div class="font-semibold text-gray-900">{{ $jobCard->vehicle->registration_no ?? 'NON-RTO' }}</div>
                            <div class="text-gray-600">{{ $jobCard->vehicle->vehicleModel?->vehicleBrand?->name }} {{ $jobCard->vehicle->vehicleModel?->name }}</div>
                        </div>
                        <div>
                            <div class="font-medium text-gray-500 text-xs uppercase tracking-wider mb-1">Job Date / Status</div>
                            <div class="font-semibold text-gray-900">{{ $jobCard->job_date->format('d M Y') }}</div>
                            <div class="mt-1">
                                <x-badge :color="match ($jobCard->status) {
                                    'delivered' => 'green',
                                    'ready_for_delivery' => 'primary',
                                    'in_progress' => 'purple',
                                    'waiting_for_parts' => 'amber',
                                    'cancelled' => 'red',
                                    default => 'gray',
                                }">
                                    {{ ucwords(str_replace('_', ' ', $jobCard->status)) }}
                                </x-badge>
                            </div>
                        </div>
                    </div>

                    @if($jobCard->complaints->count() > 0)
                        <div class="mt-4 border-t pt-4">
                            <div class="font-medium text-gray-500 text-xs uppercase tracking-wider mb-2">Complaints</div>
                            <ul class="list-disc list-inside text-sm text-gray-700 space-y-1">
                                @foreach($jobCard->complaints as $complaint)
                                    <li>{{ $complaint->description }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <form action="{{ route('job-cards.assign-update', $jobCard) }}" method="POST" class="space-y-6">
                    @csrf

                    <!-- Assignment -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold text-gray-900 mb-4">Assignment</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <x-select name="assigned_technician_id" label="Assigned Technician" :placeholder="'-- Select Technician --'"
                                      :value="$jobCard->assigned_technician_id"
                                      :options="$employees->pluck('name', 'id')->toArray()" />
                            <x-select name="supervisor_id" label="Supervisor" :placeholder="'-- Select Supervisor --'"
                                      :value="$jobCard->supervisor_id"
                                      :options="$employees->pluck('name', 'id')->toArray()" />
                        </div>
                    </div>

                    <!-- Parts Section -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="font-semibold text-gray-900">Parts</h3>
                            <button type="button" @click="addPart()" class="text-sm text-primary-600 hover:text-primary-800">+ Add Part</button>
                        </div>
                        <div class="grid grid-cols-12 gap-2 mb-1 text-xs text-gray-500">
                            <div class="col-span-3">Part</div>
                            <div class="col-span-1">Qty</div>
                            <div class="col-span-2">MRP</div>
                            <div class="col-span-2">Labor</div>
                            <div class="col-span-1">Warranty</div>
                            <div class="col-span-2">Line Total</div>
                            <div class="col-span-1"></div>
                        </div>
                        <template x-for="(part, index) in parts" :key="index">
                            <div class="grid grid-cols-12 gap-2 mb-2 items-end">
                                <div class="col-span-3">
                                    <select :name="`parts[${index}][spare_part_id]`" x-model="part.spare_part_id" @change="autofillPart(index, $event)"
                                            class="block w-full text-sm rounded-md border-gray-300 shadow-sm" required>
                                        <option value="">-- Select Part --</option>
                                        @foreach ($spareParts as $sp)
                                            <option value="{{ $sp->id }}" data-mrp="{{ $sp->mrp }}" data-labor="{{ $sp->labor_charge }}" data-gst="{{ $sp->gst_rate }}">
                                                {{ $sp->part_name }} ({{ $sp->part_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <input type="number" :name="`parts[${index}][quantity]`" x-model="part.quantity" @input="calculatePartLine(index)"
                                           placeholder="Qty" class="block w-full text-sm rounded-md border-gray-300 shadow-sm" required min="1">
                                </div>
                                <div class="col-span-2">
                                    <input type="number" step="0.01" :name="`parts[${index}][mrp]`" x-model="part.mrp"
                                           placeholder="MRP" readonly
                                           class="block w-full text-sm rounded-md border-gray-300 bg-gray-100 shadow-sm cursor-not-allowed" title="MRP is set from the spare part master and cannot be edited">
                                </div>
                                <div class="col-span-2">
                                    <input type="number" step="0.01" :name="`parts[${index}][labor_charge]`" x-model="part.labor_charge" @input="calculatePartLine(index)"
                                           placeholder="Labor" class="block w-full text-sm rounded-md border-gray-300 shadow-sm" required min="0">
                                </div>
                                <div class="col-span-1">
                                    <label class="flex items-center justify-center">
                                        <input type="checkbox" :name="`parts[${index}][under_warranty]`" value="1" x-model="part.under_warranty"
                                               @change="calculatePartLine(index)" class="rounded border-gray-300 text-primary-600 shadow-sm">
                                    </label>
                                </div>
                                <div class="col-span-2 text-right text-sm font-medium" x-text="'{{ config('garage.currency_symbol') }}' + part.lineTotal.toFixed(2)"></div>
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
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="font-semibold text-gray-900">Labour</h3>
                            <button type="button" @click="addLabour()" class="text-sm text-primary-600 hover:text-primary-800">+ Add Labour</button>
                        </div>
                        <div class="grid grid-cols-12 gap-2 mb-1 text-xs text-gray-500">
                            <div class="col-span-4">Service</div>
                            <div class="col-span-3">Technician</div>
                            <div class="col-span-1">Qty</div>
                            <div class="col-span-2">Rate</div>
                            <div class="col-span-1">Line Total</div>
                            <div class="col-span-1"></div>
                        </div>
                        <template x-for="(labour, index) in labours" :key="index">
                            <div class="grid grid-cols-12 gap-2 mb-2 items-end">
                                <div class="col-span-4">
                                    <select :name="`labours[${index}][labour_master_id]`" x-model="labour.labour_master_id" @change="autofillLabour(index, $event)"
                                            class="block w-full text-sm rounded-md border-gray-300 shadow-sm" required>
                                        <option value="">-- Select Service --</option>
                                        @foreach ($labourMasters as $lm)
                                            <option value="{{ $lm->id }}" data-rate="{{ $lm->standard_rate }}" data-taxable="{{ $lm->taxable ? '1' : '0' }}" data-gst="{{ $lm->taxable ? $lm->gst_rate : 0 }}">
                                                {{ $lm->service_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <select :name="`labours[${index}][technician_id]`" x-model="labour.technician_id"
                                            class="block w-full text-sm rounded-md border-gray-300 shadow-sm">
                                        <option value="">-- None --</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-1">
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
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold text-gray-900 mb-4">Totals & GST</h3>
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

                    <div class="flex justify-end gap-3">
                        <x-button variant="secondary" :href="route('job-cards.show', $jobCard)">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            <x-heroicon-o-check-badge class="w-4 h-4 mr-1.5" />
                            Save & Bill
                        </x-button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script>
        function assignForm(initial = @json($initialData)) {
            return {
                assignedTechnicianId: initial.assigned_technician_id || '',
                supervisorId: initial.supervisor_id || '',
                parts: initial.parts || [],
                labours: initial.labours || [],
                partsSubtotal: 0,
                laboursSubtotal: 0,
                subtotal: 0,
                discountPercent: parseFloat(initial.discount_percent || 0),
                discountAmount: parseFloat(initial.discount_amount || 0),
                gstApplicable: !!initial.gst_applicable,
                grandTotal: 0,
                roundOffAmount: 0,

                init() {
                    // Re-price every existing line from the master so the screen
                    // always reflects what will be charged on save.
                    this.parts.forEach((part, index) => this.calculatePartLine(index));
                    this.calculateTotals();
                },

                addPart() {
                    this.parts.push({spare_part_id: '', quantity: 1, mrp: 0, labor_charge: 0, under_warranty: false, gstRate: 0, lineTotal: 0});
                    this.calculateTotals();
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
                    const rawTotal = (parseFloat(part.quantity) || 0) * ((parseFloat(part.mrp) || 0) + (parseFloat(part.labor_charge) || 0));
                    part.lineTotal = part.under_warranty ? 0 : rawTotal;
                    this.calculateTotals();
                },

                addLabour() {
                    this.labours.push({labour_master_id: '', technician_id: '', qty: 1, rate: 0, gstRate: 0, lineTotal: 0});
                    this.calculateTotals();
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
