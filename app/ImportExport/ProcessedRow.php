<?php

namespace App\ImportExport;

use App\ImportExport\Enums\RowAction;
use App\ImportExport\Enums\Severity;

/**
 * One validated spreadsheet row and the plan for what will happen to it.
 */
class ProcessedRow
{
    /** @param array<string, mixed> $source Raw cell values keyed by header name. */
    /** @param array<string, mixed> $data Normalized/coerced values keyed by field name. */
    /** @param array<string, mixed> $display Interpreted values keyed by field name (for the preview). */
    /** @param ValidationIssue[] $issues */
    public function __construct(
        public readonly int $rowNumber,
        public array $source,
        public array $data,
        public array $display,
        public RowAction $action,
        public ?int $matchedId = null,
        public array $issues = [],
    ) {}

    public function addIssue(ValidationIssue $issue): void
    {
        $this->issues[] = $issue;
    }

    public function hasErrors(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->severity === Severity::ERROR) {
                return true;
            }
        }

        return false;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->severity === Severity::WARNING) {
                return true;
            }
        }

        return false;
    }
}
