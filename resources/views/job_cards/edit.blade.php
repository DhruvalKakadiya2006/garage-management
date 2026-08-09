<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-wrench-screwdriver class="w-6 h-6 text-primary-600" />
            Edit Job Card {{ $jobCard->job_card_no }}
        </h2>
    </x-slot>

    @php
        $initialData = [
            'customer_id' => $jobCard->customer_id,
            'vehicle_id' => $jobCard->vehicle_id,
            'complaints' => $jobCard->complaints->map(fn($c) => [
                'description' => $c->description,
            ])->values(),
        ];

        $vehicleData = $vehicles->map(fn($v) => [
            'id' => $v->id,
            'customer_id' => $v->customer_id,
            'registration_no' => $v->registration_no,
            'brand' => $v->vehicleModel?->vehicleBrand?->name,
            'model' => $v->vehicleModel?->name,
        ])->values();
    @endphp

    <div class="py-6" x-data="jobCardForm()">
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
                                 :value="$jobCard->job_date->format('Y-m-d')" />
                        <x-input name="odometer_reading_km" label="Odometer Reading (km)" type="number" min="0"
                                 :value="$jobCard->odometer_reading_km" />
                    </div>

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

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-select name="job_type" label="Job Type" :required="true" :value="$jobCard->job_type"
                                      :options="config('garage.option_lists.job_type')" />
                        </div>
                        <x-select name="status" label="Status" :required="true" :value="$jobCard->status"
                                  :options="config('garage.option_lists.job_card_status')" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="promised_delivery_date" label="Promised Delivery Date" type="date"
                                 :value="$jobCard->promised_delivery_date?->format('Y-m-d')" />
                        <x-input name="promised_delivery_time" label="Promised Delivery Time" type="time"
                                 :value="$jobCard->promised_delivery_time ? substr($jobCard->promised_delivery_time, 0, 5) : ''" />
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
                        <x-input name="remarks" label="Remarks" type="textarea" rows="3" :value="$jobCard->remarks" />
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
        function jobCardForm(initial = @json($initialData)) {
            const allVehicles = @json($vehicleData);

            return {
                allVehicles: allVehicles,
                selectedCustomer: initial.customer_id || '',
                filteredVehicles: [],
                vehicleId: initial.vehicle_id || '',
                complaints: initial.complaints || [],

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
