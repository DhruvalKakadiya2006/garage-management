<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
            <x-heroicon-o-book-open class="w-6 h-6 text-primary" />
            Edit Brand
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <form action="{{ route('vehicle-brands.update', $vehicleBrand) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <x-input name="name" label="Brand Name" :required="true" :value="$vehicleBrand->name" />

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button variant="secondary" :href="route('vehicle-catalog.index')">
                            Cancel
                        </x-button>
                        <x-button type="submit">
                            Update Brand
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
