<?php

namespace App\ImportExport\Contracts;

use App\ImportExport\ProcessedRow;
use Illuminate\Support\Collection;

/**
 * Per-entity contract that describes how a spreadsheet maps to a model.
 *
 * One definition drives BOTH import and export so common logic is shared. The
 * ImportService orchestrates everything else (file reading, structure checks,
 * row validation, relationship resolution, modes, transactions) — definitions
 * never touch the database outside the apply hooks.
 */
interface ImportDefinition
{
    /** Stable key used in URLs and the config registry. */
    public function key(): string;

    /** Human label shown in the UI. */
    public function label(): string;

    public function description(): ?string;

    /**
     * Column specs, in file order.
     *
     * Each spec:
     *  - header:       exact header name expected in the file
     *  - field:        model attribute / normalized key
     *  - label:        human label for errors and the preview
     *  - required:     bool
     *  - type:         string|date|datetime|integer|decimal|boolean|enum
     *  - enum:         array of allowed values (for type=enum)
     *  - max_length:   int (for type=string)
     *  - unique_in_file: bool — flag duplicate values within the uploaded file
     *  - rules:        extra Laravel validation rules for the field
     *
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array;

    /**
     * Relationship references, resolved after the column loop has normalized
     * every source field.
     *
     * Each spec:
     *  - entity:   registry key (e.g. "customers") OR a definition class name.
     *              Class names resolve through ImportRegistry::definitionForClass
     *              so private reference entities need not be top-level entities.
     *  - field:    target model attribute the resolved id is written to
     *              (e.g. 'customer_id')
     *  - keys:     map of row field => related entity import key used for the
     *              match, e.g. ['customer_external_id' => 'external_ref']
     *  - required: bool — when true, a blank reference is an error
     *
     * @return array<int, array{entity: string, field: string, keys: array<string, string>, required?: bool}>
     */
    public function relations(): array;

    /**
     * Cross-field validation hook called once per processed row, after columns
     * and relations have been resolved. Add ValidationIssue entries directly
     * to the row; ERROR issues prevent the row from being applied.
     */
    public function validateRow(ProcessedRow $row): void;

    /**
     * Fields (not headers) used to match an existing record.
     *
     * The first non-empty key wins. Keys that are unique in the DB (e.g.
     * external_ref, mobile, job_card_no) give an unambiguous match; natural
     * keys that are not unique are still supported and ambiguous matches are
     * flagged rather than guessed.
     *
     * @return array<int, string>
     */
    public function importKeys(): array;

    /**
     * Find candidate existing records for the given normalized row values.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, object>
     */
    public function resolveCandidates(array $data);

    /** Persist a new record. Must run inside the import transaction. */
    public function applyCreate(array $data): object;

    /** Persist updates to an existing record. Must run inside the import transaction. */
    public function applyUpdate(object $record, array $data): object;

    /** Header => attribute pairs written on export. */
    public function exportColumns(): array;

    /**
     * Base query whose rows are exported. Accepts optional filters so an
     * export can mirror the current list-page filter (e.g. status, date range).
     */
    public function exportQuery(array $filters = []);

    /** Base name for the exported file (an extension is appended). */
    public function exportFileName(): string;
}
