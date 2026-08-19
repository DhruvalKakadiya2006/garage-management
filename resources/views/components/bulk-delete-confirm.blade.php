@props([
    'name' => 'bulk-delete-confirm',
    'title' => 'Delete selected record(s)?',
    'confirmLabel' => 'Delete',
    'message' => 'Records linked to existing documents will be skipped.',
])

{{-- Shared bulk-delete confirmation dialog for the multi-select table pattern.
     Shows the count of records about to be deleted and calls the shared
     `bulkDelete()` method on the enclosing `tableSelection()` Alpine scope,
     which sends ONE request with all selected ids[] (never one per row). The
     modal `name` defaults to `bulk-delete-confirm`; give each independent
     selection scope on a page a unique name. --}}
<x-modal :name="$name" maxWidth="sm">
    <div class="p-6">
        <div class="flex items-start gap-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-red-600" />
            </div>
            <div class="text-sm text-gray-500">
                <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
                <p class="mt-1">
                    This will permanently delete
                    <span x-text="selectedCount" class="font-medium text-gray-900"></span>
                    selected record(s).
                </p>
                <p class="mt-1">{{ $message }}</p>
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <x-button variant="secondary" type="button" x-bind:disabled="busy"
                      @click="$dispatch('close-modal', 'bulk-delete-confirm')">
                Cancel
            </x-button>
            <x-button variant="danger" type="button" x-bind:disabled="busy" @click="bulkDelete()">
                <span x-show="busy" x-cloak>
                    <x-heroicon-o-arrow-path class="w-4 h-4 animate-spin" />
                </span>
                {{ $confirmLabel }}
            </x-button>
        </div>
    </div>
</x-modal>