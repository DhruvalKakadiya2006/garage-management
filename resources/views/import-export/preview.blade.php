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
                <x-heroicon-o-magnifying-glass class="w-6 h-6 text-primary" />
                Preview Import
            </h2>
            <x-button variant="secondary" :href="route('import-export.index')">
                <x-heroicon-o-arrow-left class="w-4 h-4" />
                Back
            </x-button>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
                <span class="font-medium text-gray-800">{{ $report->entityLabel }}</span>
                <span class="text-gray-400">·</span>
                <span>{{ $report->originalFileName }}</span>
                <span class="text-gray-400">·</span>
                <x-badge color="primary">{{ $report->mode->label() }}</x-badge>
                <x-badge color="gray">Dry run — nothing has been saved</x-badge>
            </div>

            @if ($report->structureIssues !== [])
                <x-card title="File checks">
                    @include('import-export._issues', ['issues' => $report->structureIssues])
                </x-card>
            @endif

            <div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
                <div class="rounded-lg border border-border bg-card p-4 text-center">
                    <div class="text-2xl font-semibold text-gray-900">{{ $report->totalRows() }}</div>
                    <div class="text-xs text-muted-foreground mt-1">Total rows</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4 text-center">
                    <div class="text-2xl font-semibold text-green-600">{{ $report->plannedCreates() }}</div>
                    <div class="text-xs text-muted-foreground mt-1">To create</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4 text-center">
                    <div class="text-2xl font-semibold text-amber-600">{{ $report->plannedUpdates() }}</div>
                    <div class="text-xs text-muted-foreground mt-1">To update</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4 text-center">
                    <div class="text-2xl font-semibold text-gray-500">{{ $report->plannedSkips() }}</div>
                    <div class="text-xs text-muted-foreground mt-1">To skip</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4 text-center">
                    <div class="text-2xl font-semibold text-red-600">{{ $report->errorCount() }}</div>
                    <div class="text-xs text-muted-foreground mt-1">Errors</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4 text-center">
                    <div class="text-2xl font-semibold text-amber-600">{{ $report->warningCount() }}</div>
                    <div class="text-xs text-muted-foreground mt-1">Warnings</div>
                </div>
            </div>

            @if ($report->rows !== [])
                <x-card title="Row preview">
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
                                @foreach ($report->rows as $row)
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
                                            @if ($row->issues !== [])
                                                <ul class="space-y-1">
                                                    @foreach ($row->issues as $issue)
                                                        <li class="flex items-start gap-1.5 text-xs text-gray-600">
                                                            <x-badge :color="$issue->isError() ? 'red' : 'amber'" class="shrink-0">{{ $issue->severity->value }}</x-badge>
                                                            <span>{{ $issue->message }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
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
                    <span>Rows with issues are listed below and in the downloadable report.</span>
                    <x-button variant="secondary" :href="route('import-export.error-report', $token)">
                        <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                        Download error report
                    </x-button>
                </div>
            @endif

            @if ($report->canConfirm())
                <form method="POST" action="{{ route('import-export.confirm', $token) }}">
                    @csrf
                    <div class="flex items-center justify-between rounded-lg border border-primary-200 bg-primary-50 px-4 py-3">
                        <div class="text-sm text-primary-800">
                            <span class="font-semibold">{{ $report->plannedCreates() + $report->plannedUpdates() }} row(s)</span> will be applied.
                            Rows with errors are skipped. The import runs in a single transaction — if anything fails, nothing is saved.
                        </div>
                        <x-button type="submit">
                            <x-heroicon-o-check class="w-4 h-4" />
                            Confirm Import
                        </x-button>
                    </div>
                </form>
            @elseif ($report->hasFatalStructureErrors())
                <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                    The file has structural problems (above). Correct the file and upload it again.
                </div>
            @elseif ($report->totalRows() === 0 || $report->plannedCreates() + $report->plannedUpdates() === 0)
                <div class="rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm text-gray-600">
                    Nothing to import — no rows were added or updated.
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
