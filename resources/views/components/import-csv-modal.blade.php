@props([
    'name',
    'entity',
    'title',
])

@php
    $details = app(\App\ImportExport\ImportRegistry::class)->details($entity);
    $limits = [
        'max_file_size' => config('import-export.max_file_size_bytes'),
        'allowed_extensions' => config('import-export.allowed_extensions', ['csv']),
        'max_rows' => config('import-export.max_rows'),
    ];
@endphp

{{-- Shared "Import CSV" dialog for a module's own index page. The entity is
     fixed per module (no chooser), so the upload always POSTs to the shared
     preview route with that entity key. Open it from a button on the page:
     @click="$dispatch('open-modal', '{{ $name }}')" --}}
<x-modal :name="$name" maxWidth="lg">
    <div class="p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10">
                <x-heroicon-o-arrow-up-tray class="h-5 w-5 text-primary" />
            </div>
            <div>
                <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
                <p class="text-sm text-gray-500">Upload a CSV to preview it first — nothing is written until you confirm.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('import-export.preview') }}" enctype="multipart/form-data"
              class="space-y-4">
            @csrf
            <input type="hidden" name="entity" value="{{ $entity }}">

            <div>
                <div class="mb-2 text-sm font-medium text-gray-700">How should matching records be handled?</div>
                <div class="space-y-2">
                    @foreach ($details['mode_descriptions'] as $mode)
                        <label class="flex items-start gap-2 text-sm cursor-pointer">
                            <input type="radio" name="mode" value="{{ $mode['value'] }}"
                                   class="mt-0.5 h-4 w-4 rounded-full border-input text-primary focus:ring-ring"
                                   {{ $loop->first ? 'checked' : '' }}>
                            <span>
                                <span class="font-medium text-gray-800">{{ $mode['label'] }}</span>
                                <span class="block text-xs text-gray-500">{{ $mode['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <x-input-label for="import-file-{{ $entity }}" value="CSV file (.csv)" />
                <input id="import-file-{{ $entity }}" type="file" name="file" accept=".csv"
                       class="mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border file:border-border file:bg-muted file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-accent" />
            </div>

            <p class="text-xs text-muted-foreground">
                {{ ucfirst(implode(', ', $limits['allowed_extensions'])) }} only, up to
                {{ round($limits['max_file_size'] / 1024 / 1024, 1) }} MB and
                {{ number_format($limits['max_rows']) }} rows. The downloadable template uses the exact same columns.
            </p>

            <div class="mt-6 flex justify-end gap-2">
                <x-button variant="secondary" type="button" @click="$dispatch('close-modal', '{{ $name }}')">
                    Cancel
                </x-button>
                <x-button type="submit">
                    <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                    Preview Import
                </x-button>
            </div>
        </form>
    </div>
</x-modal>