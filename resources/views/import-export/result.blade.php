@php
    use App\ImportExport\Enums\RowAction;

    $previewColumns = match ($report->entityKey) {
        'job_cards' => [
            ['field' => 'job_card_no', 'label' => 'Job No.'],
            ['field' => 'customer_id', 'label' => 'Customer'],
            ['field' => 'vehicle_id', 'label' => 'Vehicle'],
            ['field' => 'assigned_technician_id', 'label' => 'Technician'],
        ],
        'customers' => [
            ['field' => 'external_ref', 'label' => 'External Ref'],
            ['field' => 'mobile', 'label' => 'Mobile'],
        ],
        default => [
            ['field' => 'external_ref', 'label' => 'External Ref'],
        ],
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-check-circle class="w-6 h-6 text-primary" />
                Import Result
            </h2>
            <x-button variant="secondary" :href="route('import-export.index')">
                <x-heroicon-o-arrow-left class="w-4 h-4" />
                Back
            </x-button>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
                <span class="font-medium text-gray-800">{{ $report->entityLabel }}</span>
                <span class="text-gray-400">·</span>
                <span>{{ $report->originalFileName }}</span>
                <span class="text-gray-400">·</span>
                <x-badge color="primary">{{ $report->mode->label() }}</x-badge>
            </div>

            @if ($report->failed())
                <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-4 text-sm text-red-800">
                    <div class="flex items-center gap-2 font-semibold mb-1">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                        Import rolled back
                    </div>
                    <p>{{ $report->failureMessage }}</p>
                    <p class="mt-1 text-xs text-red-700">No changes were saved. You can upload the file again.</p>
                </div>
            @else
                <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-4 text-sm text-green-800">
                    <div class="flex items-center gap-2 font-semibold mb-1">
                        <x-heroicon-o-check-circle class="w-5 h-5" />
                        Import complete
                    </div>
                    <p>{{ $report->appliedCreates }} created · {{ $report->appliedUpdates }} updated.</p>
                </div>
            @endif

            <div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
                <div class="rounded-lg border border-border bg-card p-4 text-center">
                    <div class="text-2xl font-semibold text-gray-900">{{ $report->totalRows() }}</div>
                    <div class="text-xs text-muted-foreground mt-1">Total rows</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4 text-center">
                    <div class="text-2xl font-semibold text-green-600">{{ $report->appliedCreates }}</div>
                    <div class="text-xs text-muted-foreground mt-1">Created</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4 text-center">
                    <div class="text-2xl font-semibold text-amber-600">{{ $report->appliedUpdates }}</div>
                    <div class="text-xs text-muted-foreground mt-1">Updated</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4 text-center">
                    <div class="text-2xl font-semibold text-gray-500">{{ max(0, $report->totalRows() - $report->appliedCreates - $report->appliedUpdates) }}</div>
                    <div class="text-xs text-muted-foreground mt-1">Skipped</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4 text-center">
                    <div class="text-2xl font-semibold text-amber-600">{{ $report->warningCount() }}</div>
                    <div class="text-xs text-muted-foreground mt-1">Warnings</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4 text-center">
                    <div class="text-2xl font-semibold text-red-600">{{ $report->errorCount() }}</div>
                    <div class="text-xs text-muted-foreground mt-1">Errors</div>
                </div>
            </div>

            @if ($report->rowsWithIssues() !== [])
                <x-card title="Row-level details">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-border text-sm">
                            <thead>
                                <tr class="text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                    <th class="px-3 py-2">Row</th>
                                    <th class="px-3 py-2">Action</th>
                                    @foreach ($previewColumns as $col)
                                        <th class="px-3 py-2">{{ $col['label'] }}</th>
                                    @endforeach
                                    <th class="px-3 py-2">Issues</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($report->rowsWithIssues() as $row)
                                    <tr class="align-top">
                                        <td class="px-3 py-2 text-gray-500">{{ $row->rowNumber }}</td>
                                        <td class="px-3 py-2">
                                            <x-badge :color="$row->action === RowAction::CREATE ? 'green' : ($row->action === RowAction::UPDATE ? 'amber' : 'gray')">
                                                {{ $row->action->label() }}
                                            </x-badge>
                                        </td>
                                        @foreach ($previewColumns as $col)
                                            <td class="px-3 py-2 text-gray-800">{{ $row->display[$col['field']] ?? '—' }}</td>
                                        @endforeach
                                        <td class="px-3 py-2">
                                            <ul class="space-y-1">
                                                @foreach ($row->issues as $issue)
                                                    <li class="flex items-start gap-1.5 text-xs text-gray-600">
                                                        <x-badge :color="$issue->isError() ? 'red' : 'amber'" class="shrink-0">{{ $issue->severity->value }}</x-badge>
                                                        <span>{{ $issue->message }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

            @if ($report->errorReportPath)
                <div class="flex items-center justify-between rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <span>Download the full list of issues.</span>
                    <x-button variant="secondary" :href="route('import-export.error-report', $report->sessionToken)">
                        <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                        Download error report
                    </x-button>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>