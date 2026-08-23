@props([
    'bulkEndpoint' => null,
    'bulkExportEndpoint' => null,
    'bulkActionLabel' => 'Delete',
    'bulkModalTitle' => 'Delete selected record(s)?',
    'bulkModalConfirmLabel' => 'Delete',
    'bulkConfirmMessage' => 'Records linked to existing documents will be skipped.',
    'modalName' => 'bulk-delete-confirm',
])

{{-- Contextual action bar for the shared multi-select table pattern. Renders
     inside a `tableSelection()` Alpine scope.

     Visibility rules (from the persistent context):
       - 0 selected  -> bar hidden entirely
       - 1 selected  -> single-record actions (`single` slot) AND bulk actions
       - 2+ selected -> single-record actions disappear entirely; only bulk
                        actions remain

     The `single` slot holds record-specific actions (e.g. Edit, Create Job
     Card); the `bulk` slot holds any additional batch actions. When a
     `bulkExportEndpoint` is given, a shared "Export selected" button streams
     the selected rows to CSV through the existing ExportService. The "N
     selected" indicator, Clear selection control, and (when a
     `bulkEndpoint` is given) the bulk Delete button + confirmation modal are
     all shared. --}}
<div>
    <div x-show="result" x-cloak x-transition.opacity.duration.150
         :class="resultType === 'warning'
             ? 'bg-amber-50 border-amber-300 text-amber-800'
             : (resultType === 'error' ? 'bg-red-100 border-red-300 text-red-800' : 'bg-green-100 border-green-300 text-green-800')"
         class="mb-4 flex items-center justify-between gap-3 rounded-md border px-4 py-3 text-sm">
        <span x-text="resultMessage"></span>
        <button type="button" @click="dismissResult()"
                class="shrink-0 text-muted-foreground transition-colors duration-150 hover:text-gray-700">
            <x-heroicon-o-x-mark class="w-4 h-4" />
        </button>
    </div>

    <div x-show="selectedCount > 0" x-transition.opacity.duration.150
         class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 shadow-sm">
        <span class="mr-auto text-sm font-medium text-primary-800">
            <span x-text="selectedCount" class="font-semibold"></span>
            selected
        </span>

        <div x-show="selectedCount === 1" x-transition.opacity.duration.150
             class="flex flex-wrap items-center gap-2">
            {{ $single ?? '' }}
        </div>

        {{ $bulk ?? '' }}

        @if ($bulkExportEndpoint)
            <x-button variant="secondary" type="button" @click="exportSelected()">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                Export selected
            </x-button>
        @endif

        @if ($bulkEndpoint)
            @can('admin')
                <x-button variant="danger" type="button" @click="$dispatch('open-modal', '{{ $modalName }}')">
                    <x-heroicon-o-trash class="w-4 h-4" />
                    {{ $bulkActionLabel }}
                </x-button>
            @endcan
        @endif

        <x-button variant="ghost" type="button" @click="clearSelection()">
            Clear selection
        </x-button>
    </div>

    @if ($bulkEndpoint)
        @can('admin')
            <x-bulk-delete-confirm
                :name="$modalName"
                :title="$bulkModalTitle"
                :confirm-label="$bulkModalConfirmLabel"
                :message="$bulkConfirmMessage"
            />
        @endcan
    @endif
</div>