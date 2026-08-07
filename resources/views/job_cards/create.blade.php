<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-wrench-screwdriver class="w-6 h-6 text-primary-600" />
            Create New Job Card
        </h2>
    </x-slot>

    @php
        $initialData = $initialData ?? [
            'customer_id' => '',
            'vehicle_id' => '',
            'complaints' => [],
        ];

        $customerData = $customers->map(fn ($c) => [
            'id' => (string) $c->id,
            'display_name' => $c->display_name,
            'mobile' => $c->mobile,
        ])->values();

        $vehicleData = $vehicles->map(fn ($v) => [
            'id' => (string) $v->id,
            'customer_id' => (string) $v->customer_id,
            'registration_no' => $v->registration_no,
            'brand' => $v->vehicle_brand,
            'model' => $v->model,
        ])->values();
    @endphp

    <div class="py-6" x-data="jobCardForm()">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <form action="{{ route('job-cards.store') }}" method="POST" class="space-y-6">
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

                    <!-- Header Fields -->
                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="job_date" label="Job Date" :required="true" type="date" :value="date('Y-m-d')" />
                        <x-input name="odometer_reading_km" label="Odometer Reading (km)" type="number" min="0" placeholder="e.g. 12500" />
                    </div>

                    <!-- Customer & Vehicle: read-only when preselected from the Customers list -->
                    <template x-if="preselected">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Customer *</label>
                                <input type="text" readonly disabled
                                       class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 shadow-sm"
                                       x-bind:value="selectedCustomerText">
                                <input type="hidden" name="customer_id" x-bind:value="selectedCustomer">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Vehicle *</label>
                                <input type="text" readonly disabled
                                       class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 shadow-sm"
                                       x-bind:value="selectedVehicleText">
                                <input type="hidden" name="vehicle_id" x-bind:value="vehicleId">
                            </div>
                        </div>
                    </template>

                    <template x-if="!preselected">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Customer *</label>
                                <select name="customer_id" x-model="selectedCustomer" @change="filterVehicles()"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                                    <option value="">-- Select Customer --</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->display_name }} — {{ $customer->mobile }}</option>
                                    @endforeach
                                </select>
                                @error('customer_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Vehicle *</label>
                                <select name="vehicle_id" x-model="vehicleId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                                    <option value="">-- Select Vehicle --</option>
                                    <template x-for="vehicle in filteredVehicles" :key="vehicle.id">
                                        <option :value="vehicle.id" x-text="`${vehicle.registration_no} - ${vehicle.brand} ${vehicle.model}`"></option>
                                    </template>
                                </select>
                                @error('vehicle_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </template>

                    <div class="grid grid-cols-2 gap-4">
                        <x-select name="job_type" label="Job Type" :required="true" :value="'paid_service'"
                                  :options="config('garage.option_lists.job_type')" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="promised_delivery_date" label="Promised Delivery Date" type="date" />
                        <x-input name="promised_delivery_time" label="Promised Delivery Time" type="time" />
                    </div>

                    <!-- Complaints Section -->
                    <div class="border-t pt-4">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="font-semibold text-gray-900">Complaints</h3>
                            <button type="button" @click="addComplaint()" class="text-sm text-primary-600 hover:text-primary-800">+ Add Complaint</button>
                        </div>
                        <div class="grid grid-cols-12 gap-2 mb-1 text-xs text-gray-500">
                            <div class="col-span-11">Description</div>
                            <div class="col-span-1"></div>
                        </div>
                        <template x-for="(complaint, index) in complaints" :key="index">
                            <div class="grid grid-cols-12 gap-2 mb-2 items-end">
                                <div class="col-span-11">
                                    <input type="text" :name="`complaints[${index}][description]`" x-model="complaint.description"
                                           placeholder="Describe the complaint" class="block w-full text-sm rounded-md border-gray-300 shadow-sm" required>
                                </div>
                                <div class="col-span-1">
                                    <button type="button" @click="removeComplaint(index)" title="Remove"
                                            class="inline-flex items-center justify-center rounded-md p-1.5 text-red-600 transition-colors duration-150 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
                                        <x-heroicon-o-x-mark class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div>
                        <x-input name="remarks" label="Remarks" type="textarea" rows="3" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('job-cards.index')">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Save & Continue to Assign
                        </x-button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script>
        function jobCardForm(initial = @json($initialData)) {
            const allCustomers = @json($customerData);
            const allVehicles = @json($vehicleData);

            return {
                allCustomers: allCustomers,
                allVehicles: allVehicles,
                selectedCustomer: initial.customer_id || '',
                filteredVehicles: [],
                vehicleId: initial.vehicle_id || '',
                complaints: initial.complaints || [],

                get preselected() {
                    return this.selectedCustomer !== '';
                },

                get selectedCustomerText() {
                    const c = this.allCustomers.find(c => c.id === this.selectedCustomer);
                    return c ? `${c.display_name} — ${c.mobile}` : '';
                },

                get selectedVehicleText() {
                    const v = this.allVehicles.find(v => v.id === this.vehicleId);
                    return v ? `${v.registration_no} - ${v.brand} ${v.model}` : '';
                },

                init() {
                    this.filterVehicles();
                },

                filterVehicles() {
                    this.filteredVehicles = this.allVehicles.filter(v => v.customer_id == this.selectedCustomer);
                },

                addComplaint() {
                    this.complaints.push({description: ''});
                },

                removeComplaint(index) {
                    this.complaints.splice(index, 1);
                },
            }
        }
    </script>
</x-app-layout>
