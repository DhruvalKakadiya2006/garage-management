<?php

namespace App\ImportExport\Exceptions;

use RuntimeException;

/**
 * Base exception for import/export failures that should be surfaced to the
 * user as a friendly message (wrong format, oversized file, stale session, ...).
 */
class ImportException extends RuntimeException
{
    public static function unsupportedFormat(string $extension): self
    {
        return new self("Unsupported file format '{$extension}'. Only CSV files are supported.");
    }

    public static function unsupportedMime(string $mime): self
    {
        return new self("The uploaded file type '{$mime}' is not allowed.");
    }

    public static function tooLarge(int $bytes, int $maxBytes): self
    {
        return new self(sprintf(
            'The uploaded file is %d KB, which exceeds the %d KB limit.',
            (int) ceil($bytes / 1024),
            (int) ceil($maxBytes / 1024),
        ));
    }

    public static function staleSession(): self
    {
        return new self('This import session is no longer valid. Please upload the file again.');
    }
}