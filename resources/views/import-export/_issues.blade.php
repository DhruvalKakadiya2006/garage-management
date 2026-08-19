@php
    use App\ImportExport\Enums\Severity;
@endphp

@props(['issues'])

<div class="divide-y divide-border">
    @forelse ($issues as $issue)
        <div class="flex items-start gap-3 py-2 text-sm">
            <x-badge :color="$issue->isError() ? 'red' : ($issue->severity === Severity::WARNING ? 'amber' : 'gray')" class="mt-0.5 shrink-0">
                {{ $issue->severity->value }}
            </x-badge>
            <div class="min-w-0">
                <p class="text-gray-800">{{ $issue->message }}</p>
                @if ($issue->field || $issue->value !== null)
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        @if ($issue->field)
                            <span class="font-medium">Field:</span> {{ $issue->field }}
                        @endif
                        @if ($issue->value !== null && $issue->value !== '')
                            <span class="mx-1">·</span>
                            <span class="font-medium">Value:</span> {{ is_scalar($issue->value) ? (string) $issue->value : json_encode($issue->value) }}
                        @endif
                    </p>
                @endif
            </div>
        </div>
    @empty
        <p class="py-2 text-sm text-gray-500">No issues.</p>
    @endforelse
</div>
