<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-arrow-up-tray class="w-6 h-6 text-primary" />
                Import / Export
            </h2>
            <x-button variant="secondary" :href="route('import-export.guide')">
                <x-heroicon-o-book-open class="w-4 h-4" />
                Migration Guide
            </x-button>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-md">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-md">{{ session('error') }}</div>
            @endif

            {{-- Shared format rules --}}
            <div class="rounded-lg border border-border bg-muted/40 px-4 py-4 text-sm text-gray-600">
                <div class="flex items-center gap-2 font-medium text-gray-800 mb-2">
                    <x-heroicon-o-information-circle class="w-4 h-4 text-primary" />
                    How imports work
                </div>
                <ul class="space-y-1.5 list-disc list-inside">
                    <li><span class="font-medium">File type:</span> {{ ucfirst(implode(', ', $limits['allowed_extensions'])) }} only. A CSV is best created from the exported file or the downloadable template.</li>
                    <li><span class="font-medium">Size limit:</span> up to {{ round($limits['max_file_size'] / 1024 / 1024, 1) }} MB and {{ number_format($limits['max_rows']) }} rows per file.</li>
                    <li><span class="font-medium">Preview first:</span> uploads are validated and shown as a preview. Nothing is written until you confirm.</li>
                    <li><span class="font-medium">Safety:</span> the whole import runs in one transaction — if any row fails on the database, nothing is saved. Rows with validation errors are never applied.</li>
                </ul>
            </div>

            @forelse ($entities as $entity)
                <x-card :title="$entity['label']">
                    @if ($entity['description'])
                        <p class="mb-5 text-sm text-gray-600">{{ $entity['description'] }}</p>
                    @endif

                    {{-- Format cheat sheet --}}
                    <div class="mb-5 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg border border-border p-3">
                            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-1">Required columns</div>
                            @if ($entity['required_columns'] !== [])
                                <span class="text-gray-700">{{ implode(', ', $entity['required_columns']) }}</span>
                            @else
                                <span class="text-gray-500">None — every column is optional.</span>
                            @endif
                        </div>
                        <div class="rounded-lg border border-border p-3">
                            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-1">How existing records are matched</div>
                            <span class="text-gray-700">
                                @if ($entity['import_keys'] !== [])
                                    By {{ implode(' or ', $entity['import_keys']) }}, whichever is filled in first.
                                @else
                                    Every row is treated as a new record.
                                @endif
                            </span>
                        </div>
                    </div>

                    <div class="space-y-5">
                        {{-- Import form --}}
                        <div>
                            <div class="mb-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Import</div>
                            <form method="POST" action="{{ route('import-export.preview') }}" enctype="multipart/form-data"
                                  class="space-y-4 rounded-lg border border-border p-4">
                                @csrf
                                <input type="hidden" name="entity" value="{{ $entity['key'] }}">

                                <div>
                                    <div class="mb-2 text-sm font-medium text-gray-700">How should matching records be handled?</div>
                                    <div class="space-y-2">
                                        @foreach ($entity['mode_descriptions'] as $mode)
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

                                <div class="flex flex-wrap items-end gap-3">
                                    <div class="min-w-0 flex-1">
                                        <x-input-label for="file-{{ $entity['key'] }}" value="CSV file (.csv)" />
                                        <input id="file-{{ $entity['key'] }}" type="file" name="file" accept=".csv"
                                               class="mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border file:border-border file:bg-muted file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-accent" />
                                    </div>
                                    <x-button type="submit">
                                        <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                                        Preview Import
                                    </x-button>
                                </div>
                            </form>
                        </div>

                        {{-- Export / template actions --}}
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Export</div>
                            <x-dropdown align="left" width="64">
                                <x-slot name="trigger">
                                    <x-button variant="secondary" type="button">
                                        <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                        Export
                                    </x-button>
                                </x-slot>
                                <x-slot name="content">
                                    <x-dropdown-link :href="route('import-export.export', ['entity' => $entity['key']])">
                                        <span class="inline-flex items-center gap-2">
                                            <x-heroicon-o-document-text class="w-4 h-4" />
                                            Export CSV
                                        </span>
                                    </x-dropdown-link>
                                    <div class="my-1 border-t border-border"></div>
                                    <x-dropdown-link :href="route('import-export.template', ['entity' => $entity['key']])">
                                        <span class="inline-flex items-center gap-2">
                                            <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
                                            Download Import Template (CSV)
                                        </span>
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                            <span class="text-xs text-muted-foreground">The exported file and the template use the exact same columns — an export can always be imported back.</span>
                        </div>
                    </div>
                </x-card>
            @empty
                <div class="rounded-lg border border-border bg-card p-8 text-center text-sm text-gray-500">
                    No import/export entities are configured yet.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>