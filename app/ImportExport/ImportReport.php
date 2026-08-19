<?php

namespace App\ImportExport;

use App\ImportExport\Enums\ImportMode;
use App\ImportExport\Enums\RowAction;
use App\ImportExport\Enums\Severity;

/**
 * Result of an import preview or of a confirmed import.
 *
 * The same object is used for the "dry run" (applied = false) and for the
 * confirmed run (applied = true). Nothing in this object ever writes to the
 * database — it only describes what was validated and what was applied.
 */
class ImportReport
{
    /**
     * @param ValidationIssue[] $structureIssues
     * @param ProcessedRow[] $rows
     */
    public function __construct(
        public readonly string $entityKey,
        public readonly string $entityLabel,
        public readonly string $originalFileName,
        public readonly ImportMode $mode,
        public array $structureIssues = [],
        public array $rows = [],
        public bool $applied = false,
        public ?string $sessionToken = null,
        public ?string $errorReportPath = null,
        public ?string $failureMessage = null,
        public int $appliedCreates = 0,
        public int $appliedUpdates = 0,
    ) {
    }

    // ---- File / structure ----

    public function hasFatalStructureErrors(): bool
    {
        foreach ($this->structureIssues as $issue) {
            if ($issue->isError()) {
                return true;
            }
        }

        return false;
    }

    public function structureErrorCount(): int
    {
        return count(array_filter($this->structureIssues, fn (ValidationIssue $i) => $i->isError()));
    }

    // ---- Rows ----

    public function totalRows(): int
    {
        return count($this->rows);
    }

    public function plannedCreates(): int
    {
        return count(array_filter($this->rows, fn (ProcessedRow $r) => $r->action === RowAction::CREATE));
    }

    public function plannedUpdates(): int
    {
        return count(array_filter($this->rows, fn (ProcessedRow $r) => $r->action === RowAction::UPDATE));
    }

    public function plannedSkips(): int
    {
        return count(array_filter($this->rows, fn (ProcessedRow $r) => $r->action === RowAction::SKIP));
    }

    public function errorCount(): int
    {
        return $this->countIssues(Severity::ERROR);
    }

    public function warningCount(): int
    {
        return $this->countIssues(Severity::WARNING);
    }

    public function infoCount(): int
    {
        return $this->countIssues(Severity::INFO);
    }

    public function rowsWithIssues(): array
    {
        return array_values(array_filter(
            $this->rows,
            fn (ProcessedRow $r) => $r->issues !== [],
        ));
    }

    /** Whether a user may proceed to confirm this import. */
    public function canConfirm(): bool
    {
        return ! $this->applied
            && ! $this->hasFatalStructureErrors()
            && $this->plannedCreates() + $this->plannedUpdates() > 0;
    }

    /** Rows that are safe to apply (no ERROR issues and a create/update action). */
    public function applyableRows(): array
    {
        return array_values(array_filter(
            $this->rows,
            fn (ProcessedRow $r) => ! $r->hasErrors() && in_array($r->action, [RowAction::CREATE, RowAction::UPDATE], true),
        ));
    }

    /** Whether the confirmed run fully rolled back because of a critical error. */
    public function failed(): bool
    {
        return $this->applied && $this->failureMessage !== null;
    }

    private function countIssues(Severity $severity): int
    {
        $count = 0;

        foreach ($this->structureIssues as $issue) {
            if ($issue->severity === $severity) {
                $count++;
            }
        }

        foreach ($this->rows as $row) {
            foreach ($row->issues as $issue) {
                if ($issue->severity === $severity) {
                    $count++;
                }
            }
        }

        return $count;
    }
}