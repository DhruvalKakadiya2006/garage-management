<?php

namespace App\ImportExport;

use App\ImportExport\Contracts\ImportDefinition;
use App\ImportExport\Enums\ImportMode;
use App\ImportExport\Enums\RowAction;
use App\ImportExport\Exceptions\ImportException;
use App\ImportExport\Readers\FileFormatDetector;
use App\ImportExport\Support\ValueNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Format-agnostic import pipeline.
 *
 * Flow (single code path for both preview and confirm):
 *   read → validate structure → validate rows → resolve relations → decide
 *   action per row. A preview is a dry run; a confirm re-runs the exact same
 *   pipeline from the stored upload and applies the safe rows inside one
 *   transaction (all-or-nothing on critical database errors).
 *
 * Security notes:
 *  - Uploaded files are stored byte-for-byte and never modified.
 *  - Rows with ERROR issues are never applied.
 *  - A confirmed import deletes its session and upload so it cannot be
 *    accidentally re-applied; a failed confirm keeps them for retry.
 */
class ImportService
{
    public function __construct(
        private readonly ImportRegistry $registry,
    ) {
    }

    /**
     * Dry-run an upload. Creates nothing.
     */
    public function preview(
        string $entityKey,
        string $path,
        string $originalFileName,
        ?string $mime,
        ImportMode $mode,
        string $token,
    ): ImportReport {
        $data = $this->read($path, $originalFileName, $mime);

        $report = $this->process($entityKey, $data['headers'], $data['rows'], $mode, $originalFileName, $token, false);

        if ($report->errorCount() > 0) {
            $report->errorReportPath = $this->writeErrorReport($token, $report);
        }

        return $report;
    }

    /**
     * Re-run the dry-run pipeline for an existing upload token. Used by the
     * preview page so a refresh always reflects the current database state
     * and never writes anything.
     */
    public function previewForToken(string $token): ImportReport
    {
        $session = $this->loadSession($token);

        $path = Storage::disk($this->disk())->path($session['upload_path']);

        if (! is_file($path)) {
            throw ImportException::staleSession();
        }

        return $this->preview(
            entityKey: $session['entity_key'],
            path: $path,
            originalFileName: $session['original_filename'],
            mime: null,
            mode: ImportMode::from($session['mode']),
            token: $token,
        );
    }

    /**
     * Confirm a previously previewed import by re-running the pipeline from the
     * stored upload, then applying safe rows inside a transaction.
     */
    public function confirm(string $token): ImportReport
    {
        $session = $this->loadSession($token);

        $entityKey = $session['entity_key'];
        $mode = ImportMode::from($session['mode']);
        $originalFileName = $session['original_filename'];
        $uploadPath = Storage::disk($this->disk())->path($session['upload_path']);

        if (! is_file($uploadPath)) {
            throw ImportException::staleSession();
        }

        $data = $this->read($uploadPath, $originalFileName);

        $report = $this->process($entityKey, $data['headers'], $data['rows'], $mode, $originalFileName, $token, true);

        if ($report->errorCount() > 0) {
            $report->errorReportPath = $this->writeErrorReport($token, $report);
        }

        if ($report->failed()) {
            return $report;
        }

        // Success: the import is committed. Remove the upload + session so the
        // same token cannot be used to re-apply the file.
        $disk = Storage::disk($this->disk());
        $disk->delete($session['upload_path']);
        $disk->delete($this->sessionPath($token));

        return $report;
    }

    // ---------------------------------------------------------------- helpers

    /** @return array{headers: array<int, string>, rows: array<int, array<int|string, mixed>>} */
    private function read(string $path, ?string $originalFileName, ?string $mime = null): array
    {
        $reader = (new FileFormatDetector())->readerFor($path, $originalFileName, $mime);

        return $reader->read($path);
    }

    private function process(
        string $entityKey,
        array $headers,
        array $rows,
        ImportMode $mode,
        string $originalFileName,
        string $token,
        bool $applied,
    ): ImportReport {
        $definition = $this->registry->definitionFor($entityKey);

        $report = new ImportReport(
            entityKey: $entityKey,
            entityLabel: $definition->label(),
            originalFileName: $originalFileName,
            mode: $mode,
            sessionToken: $token,
        );

        $structureIssues = $this->validateStructure($headers, $definition);

        if (count($rows) > (int) config('import-export.max_rows', 5000)) {
            $structureIssues[] = ValidationIssue::error(
                null, null, count($rows),
                'The file has too many rows. Maximum allowed is '.config('import-export.max_rows').'.'
            );
        }

        if ($rows === []) {
            $structureIssues[] = ValidationIssue::info(
                null, null, '', 'The file contains no data rows.'
            );
        }

        $report->structureIssues = $structureIssues;

        $fatal = $report->hasFatalStructureErrors();

        if (! $fatal) {
            $report->rows = $this->processRows($headers, $rows, $definition, $mode);
        }

        if ($applied && ! $fatal) {
            $this->apply($definition, $report);
        }

        return $report;
    }

    /** @return ValidationIssue[] */
    private function validateStructure(array $headers, ImportDefinition $definition): array
    {
        $issues = [];

        if ($headers === []) {
            $issues[] = ValidationIssue::error(null, null, '', 'The file is empty or has no header row.');

            return $issues;
        }

        // Duplicate headers (compared case-insensitively, like the rest of the
        // header matching).
        $seen = [];
        foreach ($headers as $header) {
            $normalized = mb_strtolower(trim((string) $header));
            if (isset($seen[$normalized])) {
                $issues[] = ValidationIssue::error(null, null, $header, "Duplicate column '{$header}'.");
            }
            $seen[$normalized] = true;
        }

        // Required columns must be present (case-insensitively).
        $present = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $headers);
        foreach ($definition->columns() as $spec) {
            if (! empty($spec['required']) && ! in_array(mb_strtolower($spec['header']), $present, true)) {
                $issues[] = ValidationIssue::error(
                    null, null, $spec['header'],
                    "Missing required column '{$spec['header']}'."
                );
            }
        }

        // Unrecognised columns are reported (never silently mapped).
        foreach ($headers as $header) {
            if ($definition->columnForHeader((string) $header) === null) {
                $issues[] = ValidationIssue::warning(
                    null, null, $header,
                    "Column '{$header}' is not recognised and will be ignored."
                );
            }
        }

        return $issues;
    }

    /** @return ProcessedRow[] */
    private function processRows(array $headers, array $rows, ImportDefinition $definition, ImportMode $mode): array
    {
        // Case-insensitive header index so "Mobile", "MOBILE" and "mobile "
        // all map to the same column spec.
        $headerIndex = [];
        foreach ($headers as $i => $header) {
            $headerIndex[mb_strtolower(trim((string) $header))] = $i;
        }

        $processed = [];
        $uniqueValues = [];

        foreach ($rows as $index => $rawRow) {
            $rowNumber = $index + 2; // row 1 is the header

            if ($definition->rowIsEmpty($rawRow)) {
                continue;
            }

            $source = [];
            foreach ($headers as $i => $header) {
                $source[$header] = $rawRow[$i] ?? '';
            }

            $data = [];
            $display = [];
            $issues = [];

            foreach ($definition->columns() as $spec) {
                $field = $spec['field'];
                $label = $spec['label'] ?? $field;
                $header = (string) $spec['header'];

                $cellIndex = $headerIndex[mb_strtolower($header)] ?? null;
                $raw = $cellIndex === null ? null : ($rawRow[$cellIndex] ?? '');

                if ($raw === null || (is_string($raw) && trim($raw) === '')) {
                    $rawValue = null;
                } else {
                    $rawValue = $raw;
                }

                [$value, $issue] = ValueNormalizer::coerce($rawValue, $spec, $rowNumber);

                $data[$field] = $value;
                $display[$field] = ValueNormalizer::display($value);

                if ($issue !== null) {
                    $issues[] = $issue;
                    continue;
                }

                // Required (only when coercion did not already fail).
                if (! empty($spec['required']) && ($value === null || $value === '')) {
                    $issues[] = ValidationIssue::error($rowNumber, $field, $raw, "{$label} is required.");
                    continue;
                }

                // Duplicate detection inside the uploaded file.
                if (! empty($spec['unique_in_file']) && $value !== null && $value !== '') {
                    $key = $field.':'.mb_strtolower((string) $value);
                    if (isset($uniqueValues[$key])) {
                        $issues[] = ValidationIssue::error(
                            $rowNumber, $field, $raw,
                            "Duplicate value '{$raw}' for {$label} — also appears on row {$uniqueValues[$key]}."
                        );
                    } else {
                        $uniqueValues[$key] = $rowNumber;
                    }
                }

                // Extra Laravel validation rules (email, phone regex, GSTIN, ...).
                // Only applied to non-empty values — a blank optional field is
                // valid, and required-ness is already enforced above.
                if (! empty($spec['rules']) && $value !== null && $value !== '') {
                    $validator = Validator::make($data, [$field => $spec['rules']]);
                    foreach ($validator->errors()->get($field) as $message) {
                        $issues[] = ValidationIssue::error($rowNumber, $field, $raw, $message);
                    }
                }
            }

            // Resolve relationship references now that every source column has
            // been normalized. The resolved id lands in $data[$field]; a human
            // label for the resolved record lands in $display[$field].
            foreach ($definition->relations() as $relation) {
                [$resolved, $relationIssue, $relationLabel] = $this->resolveRelation($relation, $data, $rowNumber);

                $data[$relation['field']] = $resolved;
                $display[$relation['field']] = $relationLabel ?? '—';

                if ($relationIssue !== null) {
                    $issues[] = $relationIssue;
                }
            }

            $row = new ProcessedRow($rowNumber, $source, $data, $display, RowAction::CREATE);

            if ($issues !== []) {
                foreach ($issues as $issue) {
                    $row->addIssue($issue);
                }
            }

            // Entity-specific cross-field validation (e.g. a vehicle that does
            // not belong to the resolved customer).
            $definition->validateRow($row);

            $this->decideAction($definition, $row, $data, $mode);

            $processed[] = $row;
        }

        return $processed;
    }

    private function decideAction(ImportDefinition $definition, ProcessedRow $row, array $data, ImportMode $mode): void
    {
        $candidates = $definition->resolveCandidates($data);

        if ($mode === ImportMode::CREATE_ONLY) {
            if ($candidates->isNotEmpty()) {
                $row->action = RowAction::SKIP;
                $row->matchedId = $candidates->first()->getKey();
                $row->addIssue(ValidationIssue::warning(
                    $row->rowNumber, null, '',
                    'A matching record already exists — skipped in Create only mode.'
                ));
            }

            return;
        }

        // CREATE_OR_UPDATE — a match must be unambiguous; never guess.
        if ($candidates->count() === 1) {
            $row->action = RowAction::UPDATE;
            $row->matchedId = $candidates->first()->getKey();

            return;
        }

        if ($candidates->count() > 1) {
            $row->action = RowAction::SKIP;
            $row->addIssue(ValidationIssue::error(
                $row->rowNumber, null, '',
                'Matches more than one existing record. Provide a unique import key (external_ref or mobile) to resolve this.'
            ));

            return;
        }

        $row->action = RowAction::CREATE;
    }

    /**
     * Resolve a reference to another entity (customer, vehicle, employee) using
     * the definition's relations() spec.
     *
     * One or more source fields map onto the related entity's import keys. A
     * blank reference is fine unless the relation is required; a reference that
     * matches nothing or matches several records is reported as an error and
     * never guessed.
     *
     * @param array{entity: string, field: string, keys: array<string, string>, required?: bool} $relation
     * @return array{0: int|string|null, 1: ValidationIssue|null, 2: string|null}
     */
    private function resolveRelation(array $relation, array $data, int $rowNumber): array
    {
        $related = $this->relatedDefinitionFor($relation['entity']);

        $candidateData = [];
        $hasValue = false;
        foreach ($relation['keys'] as $sourceField => $targetKey) {
            $value = $data[$sourceField] ?? null;
            if ($value !== null && $value !== '') {
                $hasValue = true;
            }
            $candidateData[$targetKey] = $value;
        }

        if (! $hasValue) {
            if (! empty($relation['required'])) {
                return [null, ValidationIssue::error($rowNumber, $relation['field'], '', 'This reference is required.'), null];
            }

            return [null, null, null];
        }

        $candidates = $related->resolveCandidates($candidateData);

        if ($candidates->count() === 1) {
            $candidate = $candidates->first();

            return [$candidate->getKey(), null, $this->labelForCandidate($candidate)];
        }

        $reference = implode('/', array_values(array_filter($candidateData, fn ($v) => $v !== null && $v !== '')));

        if ($candidates->count() > 1) {
            return [null, ValidationIssue::error(
                $rowNumber,
                $relation['field'],
                $reference,
                'Reference is ambiguous — matches more than one record.'
            ), null];
        }

        return [null, ValidationIssue::error(
            $rowNumber,
            $relation['field'],
            $reference,
            "No matching {$related->label()} found. Import the {$related->label()} first or fix the reference."
        ), null];
    }

    /**
     * Resolve the definition behind a relation's 'entity' value, which may be
     * either a registry key or a definition class name (private references).
     */
    private function relatedDefinitionFor(string $entity): ImportDefinition
    {
        if ($this->registry->has($entity)) {
            return $this->registry->definitionFor($entity);
        }

        if (class_exists($entity)) {
            return $this->registry->definitionForClass($entity);
        }

        throw new InvalidArgumentException("Unknown related entity '{$entity}'.");
    }

    /**
     * Best human-readable label for a resolved candidate, used in the preview.
     */
    private function labelForCandidate(object $candidate): string
    {
        foreach (['display_name', 'name', 'registration_no', 'external_ref'] as $attr) {
            if (isset($candidate->{$attr}) && $candidate->{$attr} !== '') {
                return (string) $candidate->{$attr};
            }
        }

        return '#'.$candidate->getKey();
    }

    private function apply(ImportDefinition $definition, ImportReport $report): void
    {
        DB::beginTransaction();

        try {
            $creates = 0;
            $updates = 0;

            foreach ($report->applyableRows() as $row) {
                if ($row->action === RowAction::CREATE) {
                    $definition->applyCreate($row->data);
                    $creates++;
                } elseif ($row->action === RowAction::UPDATE) {
                    // Re-resolve inside the transaction so the target is the
                    // record that exists right now, not the one seen at preview.
                    $candidates = $definition->resolveCandidates($row->data);

                    if ($candidates->count() === 1) {
                        $definition->applyUpdate($candidates->first(), $row->data);
                        $updates++;
                    } else {
                        $row->addIssue(ValidationIssue::warning(
                            $row->rowNumber, null, '',
                            'The matching record changed during import — row skipped.'
                        ));
                    }
                }
            }

            DB::commit();

            $report->applied = true;
            $report->appliedCreates = $creates;
            $report->appliedUpdates = $updates;
        } catch (\Throwable $e) {
            DB::rollBack();

            $report->applied = true;
            $report->appliedCreates = 0;
            $report->appliedUpdates = 0;
            $report->failureMessage = 'The import was rolled back: an unexpected database error occurred. No changes were saved.';

            Log::error('Import confirm failed', [
                'entity' => $definition->key(),
                'mode' => $report->mode->value,
                'file' => $report->originalFileName,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function writeErrorReport(string $token, ImportReport $report): string
    {
        $path = config('import-export.storage.error_reports').'/'.$token.'.csv';

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['Severity', 'Row', 'Field', 'Value', 'Message']);

        foreach ($report->structureIssues as $issue) {
            fputcsv($handle, [
                $issue->severity->value,
                '',
                $issue->field ?? '',
                is_scalar($issue->value) ? (string) $issue->value : '',
                $issue->message,
            ]);
        }

        foreach ($report->rows as $row) {
            foreach ($row->issues as $issue) {
                fputcsv($handle, [
                    $issue->severity->value,
                    $row->rowNumber,
                    $issue->field ?? '',
                    is_scalar($issue->value) ? (string) $issue->value : '',
                    $issue->message,
                ]);
            }
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        Storage::disk($this->disk())->put($path, $contents);

        return $path;
    }

    // ------------------------------------------------------------ sessions

    public function storeSession(string $token, array $session): void
    {
        Storage::disk($this->disk())->put(
            $this->sessionPath($token),
            json_encode($session, JSON_UNESCAPED_SLASHES),
        );
    }

    /** @return array<string, mixed> */
    private function loadSession(string $token): array
    {
        if (! Storage::disk($this->disk())->exists($this->sessionPath($token))) {
            throw ImportException::staleSession();
        }

        $session = json_decode(Storage::disk($this->disk())->get($this->sessionPath($token)), true);

        if (! is_array($session)) {
            throw ImportException::staleSession();
        }

        return $session;
    }

    private function sessionPath(string $token): string
    {
        return config('import-export.storage.sessions').'/'.$token.'.json';
    }

    private function disk(): string
    {
        return config('import-export.storage.disk', 'local');
    }
}