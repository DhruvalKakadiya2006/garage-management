<?php

namespace App\ImportExport;

use App\ImportExport\Enums\Severity;

/**
 * A single validation finding on a file, a row, or a field.
 */
class ValidationIssue
{
    public function __construct(
        public readonly Severity $severity,
        public readonly ?int $row,
        public readonly ?string $field,
        public readonly mixed $value,
        public readonly string $message,
    ) {
    }

    public static function error(?int $row, ?string $field, mixed $value, string $message): self
    {
        return new self(Severity::ERROR, $row, $field, $value, $message);
    }

    public static function warning(?int $row, ?string $field, mixed $value, string $message): self
    {
        return new self(Severity::WARNING, $row, $field, $value, $message);
    }

    public static function info(?int $row, ?string $field, mixed $value, string $message): self
    {
        return new self(Severity::INFO, $row, $field, $value, $message);
    }

    public function isError(): bool
    {
        return $this->severity === Severity::ERROR;
    }
}