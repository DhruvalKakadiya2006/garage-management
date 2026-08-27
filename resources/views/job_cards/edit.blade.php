<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-wrench-screwdriver class="w-6 h-6 text-primary" />
            Edit Job Card {{ $jobCard->job_card_no }}
        </h2>
    </x-slot>

    @php
        $initialCustomerText = $initialCustomer
            ? $initialCustomer->display_name.' — '.$initialCustomer->mobile
            : '';

        $initialVehiclesData = $initialVehicles->map(fn ($v) => [
            'id' => (string) $v->id,
            'customer_id' => (string) $v->customer_id,
            'registration_no' => $v->registration_no,
            'is_non_rto' => (bool) $v->is_non_rto,
            'brand' => $v->vehicleModel?->vehicleBrand?->name,
            'model' => $v->vehicleModel?->name,
            'label' => trim(($v->registration_no ?? 'NON-RTO').' — '.($v->vehicleModel?->vehicleBrand?->name ?? '').' '.($v->vehicleModel?->name ?? '')),
        ])->values();

        $initialData = [
            'customer_id' => old('customer_id') !== null ? (string) old('customer_id') : (string) $jobCard->customer_id,
            'vehicle_id' => old('vehicle_id') !== null ? (string) old('vehicle_id') : (string) $jobCard->vehicle_id,
            'complaints' => old('complaints') ?? $jobCard->complaints->map(fn ($c) => [
                'description' => $c->description,
            ])->values(),
        ];
    @endphp

    <div class="py-6" x-data="jobCardForm(@json($initialData), @json($initialVehiclesData))">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <form action="{{ route('job-cards.update', $jobCard) }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PUT')

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
                        <x-input name="job_date" label="Job Date" :required="true" type="date"
                                 :value="old('job_date', $jobCard->job_date->format('Y-m-d'))" />
                        <x-input name="odometer_reading_km" label="Odometer Reading (km)" type="number" min="0"
                                 :value="old('odometer_reading_km', $jobCard->odometer_reading_km)" />
                    </div>

                    <!-- Customer & Vehicle -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Customer *</label>
                            <x-searchable-combobox
                                name="customer_id"
                                endpoint="{{ route('customers.search') }}"
                                :initial-id="$initialCustomer?->id"
                                :initial-label="$initialCustomerText"
                                placeholder="Search customer by name or mobile…"
                                @combobox-selected="onCustomerSelected($event.detail)"
                                @combobox-cleared="onCustomerCleared()" />
                            @error('customer_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vehicle *</label>
                            <select name="vehicle_id" x-model="vehicleId"
                                    class="mt-1 block w-full rounded-md border border-gray-300 bg-white shadow-sm" required>
                                <option value="">-- Select Vehicle --</option>
                                <template x-for="vehicle in vehicles" :key="vehicle.id">
                                    <option :value="vehicle.id" x-text="vehicle.label"></option>
                                </template>
                            </select>
                            <p class="text-xs text-gray-500 mt-1" x-show="vehiclesLoading">Loading vehicles…</p>
                            <p class="text-xs text-amber-600 mt-1" x-show="selectedCustomer && !vehiclesLoading && vehicles.length === 0">
                                No vehicles for this customer yet.
                            </p>
                            @error('vehicle_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-select name="job_type" label="Job Type" :required="true" :value="old('job_type', $jobCard->job_type)"
                                      :options="config('garage.option_lists.job_type')" />
                        </div>
                        <x-select name="status" label="Status" :required="true" :value="old('status', $jobCard->status)"
                                  :options="config('garage.option_lists.job_card_status')" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="promised_delivery_date" label="Promised Delivery Date" type="date"
                                 :value="old('promised_delivery_date', $jobCard->promised_delivery_date?->format('Y-m-d'))" />
                        <x-input name="promised_delivery_time" label="Promised Delivery Time" type="time"
                                 :value="old('promised_delivery_time', $jobCard->promised_delivery_time ? substr($jobCard->promised_delivery_time, 0, 5) : '')" />
                    </div>

                    <!-- Complaints Section -->
                    <div class="border-t pt-4">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="font-semibold text-gray-900">Complaints</h3>
                            <button type="button" @click="addComplaint()" class="text-sm text-primary hover:text-primary-700">+ Add Complaint</button>
                        </div>
                        <div class="grid grid-cols-12 gap-2 mb-1 text-xs text-gray-500">
                            <div class="col-span-11">Description</div>
                            <div class="col-span-1"></div>
                        </div>
                        <template x-for="(complaint, index) in complaints" :key="index">
                            <div class="grid grid-cols-12 gap-2 mb-2 items-end">
                                <div class="col-span-11">
                                    <input type="text" :name="`complaints[${index}][description]`" x-model="complaint.description"
                                           placeholder="Describe the complaint" class="block w-full text-sm rounded-md border border-gray-300 bg-white shadow-sm" required>
                                </div>
                                <div class="col-span-1">
                                    <button type="button" @click="removeComplaint(index)" title="Remove"
                                            class="inline-flex items-center justify-center rounded-md p-1.5 text-red-600 transition-colors duration-150 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                        <x-heroicon-o-x-mark class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div>
                        <x-input name="remarks" label="Remarks" type="textarea" rows="3" :value="old('remarks', $jobCard->remarks)" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('job-cards.show', $jobCard)">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Update Job Card
                        </x-button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script>
        function jobCardForm(initial = @json($initialData), initialVehicles = []) {
            const vehiclesBase = @js(url('customers'));

            return {
                selectedCustomer: initial.customer_id || '',
                vehicles: initialVehicles || [],
                vehicleId: initial.vehicle_id || '',
                vehiclesLoading: false,
                complaints: initial.complaints || [],

                init() {
                    // Auto-select the only vehicle when the customer has exactly one.
                    if (this.vehicles.length === 1 && !this.vehicleId) {
                        this.vehicleId = this.vehicles[0].id;
                    }
                },

                onCustomerSelected(record) {
                    this.selectedCustomer = record.id;
                    this.vehicleId = '';
                    this.loadVehicles(record.id);
                },

                onCustomerCleared() {
                    this.selectedCustomer = '';
                    this.vehicles = [];
                    this.vehicleId = '';
                },

                async loadVehicles(customerId) {
                    this.vehiclesLoading = true;
                    this.vehicles = [];
                    this.vehicleId = '';
                    try {
                        const res = await fetch(`${vehiclesBase}/${customerId}/vehicles`, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!res.ok) throw new Error('Request failed');
                        this.vehicles = await res.json();
                        if (this.vehicles.length === 1) {
                            this.vehicleId = this.vehicles[0].id;
                        }
                    } catch (e) {
                        this.vehicles = [];
                    } finally {
                        this.vehiclesLoading = false;
                    }
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