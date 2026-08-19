<?php

namespace App\ImportExport\Readers;

use App\ImportExport\Exceptions\ImportException;

/**
 * Picks the right reader for an uploaded file based on its extension and,
 * best-effort, its declared MIME type.
 *
 * CSV is the only supported format; anything else is rejected up front.
 */
class FileFormatDetector
{
    public function readerFor(string $path, ?string $originalName = null, ?string $mime = null): SpreadsheetReaderInterface
    {
        $extension = strtolower(pathinfo($originalName ?: $path, PATHINFO_EXTENSION));

        $allowed = config('import-export.allowed_extensions', ['csv']);
        if (! in_array($extension, $allowed, true)) {
            throw ImportException::unsupportedFormat($extension !== '' ? $extension : 'unknown');
        }

        // MIME is unreliable for .csv (browsers disagree), so only reject when a
        // declared type is clearly wrong and not the generic octet-stream.
        if ($mime !== null && $mime !== '' && $mime !== 'application/octet-stream') {
            $allowedMimes = config('import-export.allowed_mime_types', []);
            if (! in_array($mime, $allowedMimes, true)) {
                throw ImportException::unsupportedMime($mime);
            }
        }

        if ($extension === 'csv') {
            return new CsvReader();
        }

        throw ImportException::unsupportedFormat($extension);
    }
}