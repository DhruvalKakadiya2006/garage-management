<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-users class="w-6 h-6 text-primary" />
            Edit Customer
        </h2>
    </x-slot>

    @php
        $derivedDisplayName = trim(preg_replace('/\s+/', ' ', "{$customer->first_name} {$customer->last_name} {$customer->surname}"));
        $displayNameTouched = (bool) ($customer->display_name && $customer->display_name !== $derivedDisplayName);
    @endphp

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('customers.update', $customer) }}" method="POST" class="space-y-4"
                      x-data="customerForm(@js([
                          'firstName' => $customer->first_name,
                          'lastName' => $customer->last_name,
                          'surname' => $customer->surname,
                          'displayName' => $customer->display_name,
                          'displayNameTouched' => $displayNameTouched,
                      ]))">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-3 gap-4">
                        <x-input name="first_name" label="First Name" :required="true" :value="$customer->first_name" x-model="firstName" />
                        <x-input name="last_name" label="Last Name" :value="$customer->last_name" x-model="lastName" />
                        <x-input name="surname" label="Surname" :value="$customer->surname" x-model="surname" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="company_name" label="Company Name" :value="$customer->company_name" />
                        <x-input name="display_name" label="Display Name" :required="true" :value="$customer->display_name"
                                 x-model="displayName" @input="markTouched()" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="mobile" label="Mobile" :required="true" :value="$customer->mobile" />
                        <x-input name="alt_mobile" label="Alt. Mobile" :value="$customer->alt_mobile" />
                    </div>

                    <x-input name="email" label="Email" type="email" :value="$customer->email" />

                    <x-input name="address" label="Address" type="textarea" :value="$customer->address" />

                    <x-input name="city" label="City" :value="$customer->city" />

                    <x-input name="gstin" label="GSTIN (optional)" :value="$customer->gstin" />

                    <x-select name="customer_type" label="Customer Type" :required="true"
                              :options="config('garage.option_lists.customer_type')"
                              :value="$customer->customer_type" />

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('customers.index')">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Update Customer
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>

    <script>
        function customerForm(initial = {}) {
            return {
                firstName: initial.firstName || '',
                lastName: initial.lastName || '',
                surname: initial.surname || '',
                displayName: initial.displayName || '',
                displayNameTouched: initial.displayNameTouched || false,

                init() {
                    this.$watch('firstName', () => this.syncDisplay());
                    this.$watch('lastName', () => this.syncDisplay());
                    this.$watch('surname', () => this.syncDisplay());
                },

                get derivedDisplayName() {
                    return [this.firstName, this.lastName, this.surname].filter(Boolean).join(' ').trim();
                },

                syncDisplay() {
                    if (!this.displayNameTouched) {
                        this.displayName = this.derivedDisplayName;
                    }
                },

                markTouched() {
                    this.displayNameTouched = true;
                },
            };
        }
    </script>
</x-app-layout>
