<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-building-office-2 class="w-6 h-6 text-primary" />
            Company Profile
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('company-profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                <x-card title="Company Details">
                    <div class="space-y-4">
                        <x-input name="company_name" label="Company Name" :required="true" :value="$profile->company_name" />

                        <x-input name="address" label="Address" type="textarea" :value="$profile->address" />

                        <div class="grid grid-cols-2 gap-4">
                            <x-input name="city" label="City" :value="$profile->city" />
                            <x-input name="state" label="State" :value="$profile->state" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <x-input name="pincode" label="PIN Code" :value="$profile->pincode" />
                            <x-input name="phone" label="Phone" :value="$profile->phone" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <x-input name="email" label="Email" type="email" :value="$profile->email" />
                            <x-input name="gstin" label="GSTIN" :value="$profile->gstin" />
                        </div>
                    </div>
                </x-card>

                <x-card title="Bank Details">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-input name="bank_name" label="Bank Name" :value="$profile->bank_name" />
                        <x-input name="bank_account_no" label="Account Number" :value="$profile->bank_account_no" />
                        <x-input name="bank_ifsc" label="IFSC Code" :value="$profile->bank_ifsc" />
                    </div>
                </x-card>

                <x-card title="Logo & Terms">
                    <div class="space-y-4">
                        @if ($logoUrl)
                            <div>
                                <x-input-label value="Current Logo" />
                                <img src="{{ $logoUrl }}" alt="Company logo"
                                     class="mt-2 h-20 w-auto object-contain border border-border rounded-md p-1">
                            </div>
                        @endif

                        <div>
                            <x-input-label value="Upload Logo" />
                            <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml"
                                   class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90">
                            <p class="mt-1 text-xs text-gray-500">PNG, JPG or SVG up to 2 MB.</p>
                            @error('logo')
                                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($profile->logo_path)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="remove_logo" value="1"
                                       class="rounded border-input text-primary shadow-sm focus:ring-ring">
                                <span class="text-sm text-gray-700">Remove current logo</span>
                            </label>
                        @endif

                        <x-input name="terms_and_conditions" label="Terms & Conditions (printed on invoices)" type="textarea"
                                 rows="4" :value="$profile->terms_and_conditions" />
                    </div>
                </x-card>

                <div class="flex justify-end gap-3">
                    <x-button type="submit">
                        Save Company Profile
                    </x-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>