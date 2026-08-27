<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-users class="w-6 h-6 text-primary" />
            Add Customer
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('customers.store') }}" method="POST" class="space-y-4"
                      x-data="customerForm()">
                    @csrf

                    <div class="grid grid-cols-3 gap-4">
                        <x-input name="first_name" label="First Name" :required="true" x-model="firstName" />
                        <x-input name="last_name" label="Last Name" x-model="lastName" />
                        <x-input name="surname" label="Surname" x-model="surname" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="company_name" label="Company Name" />
                        <x-input name="display_name" label="Display Name" placeholder="Auto-filled from name fields"
                                 x-model="displayName" @input="markTouched()" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="mobile" label="Mobile" :required="true" />
                        <x-input name="alt_mobile" label="Alt. Mobile" />
                    </div>

                    <x-input name="email" label="Email" type="email" />

                    <x-input name="address" label="Address" type="textarea" />

                    <x-input name="city" label="City" placeholder="Default: Surat" />

                    <x-input name="gstin" label="GSTIN (optional)" />

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('customers.index')">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Save Customer
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
