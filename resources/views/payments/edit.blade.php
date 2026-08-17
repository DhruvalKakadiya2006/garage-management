<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-banknotes class="w-6 h-6 text-primary-600" />
            Edit Payment
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('payments.update', $payment) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    @php
                        $payable = $payment->payable;
                        $documentLabel = $payable instanceof \App\Models\JobCard
                            ? 'Job Card ' . $payable->job_card_no
                            : ($payable ? 'Counter Invoice ' . $payable->invoice_no : 'Deleted record');
                    @endphp

                    <div class="p-4 bg-gray-50 rounded-lg text-sm text-gray-600">
                        Editing payment on <span class="font-medium text-gray-900">{{ $documentLabel }}</span>. The linked
                        document's outstanding balance recalculates automatically.
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="amount" label="Amount" type="number" step="0.01" :required="true" :value="$payment->amount" />
                        <x-select name="payment_mode" label="Payment Mode" :required="true"
                                  :options="config('garage.option_lists.payment_mode')"
                                  :value="$payment->payment_mode" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="payment_date" label="Payment Date" type="date" :required="true"
                                 :value="$payment->payment_date->format('Y-m-d')" />
                        <x-input name="reference_no" label="Reference No" :value="$payment->reference_no"
                                 placeholder="Txn ID / Cheque No" />
                    </div>

                    <x-input name="notes" label="Notes" type="textarea" rows="2" :value="$payment->notes" />

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('payments.index')">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Update Payment
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
