<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-receipt-percent class="w-6 h-6 text-primary-600" />
            New Counter Invoice
        </h2>
    </x-slot>

    <div class="py-6" x-data="counterInvoiceForm()">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <form action="{{ route('counter-invoices.store') }}" method="POST" class="space-y-6">
                    @csrf

                    @if($errors->any())
                        <div class="mb-4 bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-md">
                            <ul class="list-disc list-inside text-sm">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="invoice_date" label="Invoice Date" :required="true" type="date" :value="date('Y-m-d')" />
                    </div>

                    <!-- Customer (Optional) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                        <p class="text-xs text-gray-500 mb-2">Optional — select an existing customer, add walk-in details, or leave blank for a quick anonymous sale.</p>
                        <div class="flex gap-4">
                            <label class="flex items-center">
                                <input type="radio" name="customer_type" value="existing" x-model="customerType" class="mr-2">
                                <span class="text-sm">Existing Customer</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="customer_type" value="walkin" x-model="customerType" class="mr-2">
                                <span class="text-sm">Walk-in / Quick Sale</span>
                            </label>
                        </div>
                    </div>

                    <!-- Existing Customer -->
                    <div x-show="customerType === 'existing'" class="border-l-4 border-primary-400 pl-4">
                        <x-select name="customer_id" label="Select Customer" placeholder="-- Select Customer --">
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->name }} — {{ $customer->mobile }}
                                </option>
                            @endforeach
                        </x-select>
                    </div>

                    <!-- Walk-in Customer -->
                    <div x-show="customerType === 'walkin'" class="border-l-4 border-green-400 pl-4 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <x-input name="walkin_name" label="Walk-in Name" />
                            <x-input name="walkin_mobile" label="Walk-in Mobile" />
                        </div>
                    </div>

                    <!-- Items Section -->
                    <div class="border-t pt-4">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="font-semibold text-gray-900">Items *</h3>
                            <button type="button" @click="addItem()" class="text-sm text-primary-600 hover:text-primary-800">+ Add Item</button>
                        </div>
                        <template x-for="(item, index) in items" :key="index">
                            <div class="grid grid-cols-12 gap-2 mb-2 items-end">
                                <div class="col-span-5">
                                    <select :name="`items[${index}][spare_part_id]`" x-model="item.spare_part_id" @change="autofillPrice(index, $event)"
                                            class="block w-full text-sm rounded-md border-gray-300 shadow-sm" required>
                                        <option value="">-- Select Part --</option>
                                        @foreach ($spareParts as $part)
                                            <option value="{{ $part->id }}" data-mrp="{{ $part->mrp }}" data-gst="{{ $part->gst_rate }}">
                                                {{ $part->part_name }} ({{ $part->part_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <input type="number" :name="`items[${index}][quantity]`" x-model="item.quantity" @input="calculateItemLine(index)"
                                           placeholder="Qty" class="block w-full text-sm rounded-md border-gray-300 shadow-sm" required min="1">
                                </div>
                                <div class="col-span-2">
                                    <input type="number" step="0.01" :name="`items[${index}][mrp]`" x-model="item.mrp" @input="calculateItemLine(index)"
                                           placeholder="MRP" class="block w-full text-sm rounded-md border-gray-300 shadow-sm" required min="0">
                                </div>
                                <div class="col-span-1">
                                    <input type="number" step="0.01" :name="`items[${index}][discount_percent]`" x-model="item.discount_percent" @input="calculateItemLine(index)"
                                           placeholder="Disc%" class="block w-full text-sm rounded-md border-gray-300 shadow-sm" min="0" max="100">
                                </div>
                                <div class="col-span-1 text-right text-sm font-medium" x-text="'{{ config('garage.currency_symbol') }}' + item.lineTotal.toFixed(2)"></div>
                                <div class="col-span-1">
                                    <button type="button" @click="removeItem(index)" title="Remove"
                                            class="inline-flex items-center justify-center rounded-md p-1.5 text-red-600 transition-colors duration-150 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
                                        <x-heroicon-o-x-mark class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Totals & Payment -->
                    <div class="border-t pt-4">
                        <div class="max-w-md ml-auto space-y-3">
                            <div class="flex justify-between text-sm"><span>Subtotal:</span><span x-text="'{{ config('garage.currency_symbol') }}' + subtotal.toFixed(2)"></span></div>
                            
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" step="0.01" name="discount_percent" x-model="discountPercent" @input="calculateTotals()"
                                       placeholder="Invoice Discount %" class="text-sm rounded-md border-gray-300 shadow-sm" min="0" max="100">
                                <input type="number" step="0.01" name="discount_amount" x-model="discountAmount" @input="discountPercent=0; calculateTotals()"
                                       placeholder="Invoice Discount {{ config('garage.currency_symbol') }}" class="text-sm rounded-md border-gray-300 shadow-sm" min="0">
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

                    <div class="grid grid-cols-2 gap-4 border-t pt-4">
                        <div>
                            <x-select name="payment_mode" label="Payment Mode (Optional)" placeholder="-- Not Paid Yet --"
                                      :options="config('garage.option_lists.payment_mode')" />
                            <p class="text-xs text-gray-500 mt-1">Leave blank if unpaid. Use the Record Payment section after saving to track payments.</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('counter-invoices.index')">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Save Invoice
                        </x-button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script>
        function counterInvoiceForm(initial = {customerType: '', items: [], discountPercent: 0, discountAmount: 0, gstApplicable: false}) {
            return {
                customerType: initial.customerType || '',
                items: initial.items || [],
                subtotal: 0,
                discountPercent: initial.discountPercent || 0,
                discountAmount: initial.discountAmount || 0,
                gstApplicable: initial.gstApplicable || false,
                grandTotal: 0,
                roundOffAmount: 0,

                init() {
                    this.calculateTotals();
                },

                addItem() {
                    this.items.push({spare_part_id: '', quantity: 1, mrp: 0, discount_percent: 0, gstRate: 0, lineTotal: 0});
                },

                removeItem(index) {
                    this.items.splice(index, 1);
                    this.calculateTotals();
                },

                autofillPrice(index, event) {
                    const option = event.target.selectedOptions[0];
                    this.items[index].mrp = parseFloat(option.dataset.mrp || 0);
                    this.items[index].gstRate = parseFloat(option.dataset.gst || 0);
                    this.calculateItemLine(index);
                },

                calculateItemLine(index) {
                    const item = this.items[index];
                    const lineSubtotal = (parseFloat(item.quantity) || 0) * (parseFloat(item.mrp) || 0);
                    const discountAmount = (lineSubtotal * (parseFloat(item.discount_percent) || 0)) / 100;
                    item.lineTotal = lineSubtotal - discountAmount;
                    this.calculateTotals();
                },

                calculateTotals() {
                    this.subtotal = this.items.reduce((sum, item) => sum + (parseFloat(item.lineTotal) || 0), 0);

                    if (this.discountPercent > 0) {
                        this.discountAmount = (this.subtotal * this.discountPercent) / 100;
                    }

                    const discountAmount = parseFloat(this.discountAmount) || 0;
                    const discountRatio = this.subtotal > 0 ? discountAmount / this.subtotal : 0;
                    let cgstAmount = 0;
                    let sgstAmount = 0;

                    if (this.gstApplicable) {
                        this.items.forEach(i => {
                            const rate = parseFloat(i.gstRate) || 0;
                            if (rate > 0) {
                                const tax = (i.lineTotal * (1 - discountRatio) * rate) / 100;
                                const half = Math.round(tax / 2 * 100) / 100;
                                cgstAmount += half;
                                sgstAmount += half;
                            }
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
