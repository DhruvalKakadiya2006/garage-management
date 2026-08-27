<?php

namespace App\ImportExport\Readers;

/**
 * Reads a spreadsheet-like file into a uniform structure so the rest of the
 * pipeline never cares about the underlying format.
 *
 * A future XLSX reader (backed by PhpSpreadsheet, which needs ext-zip) only
 * has to implement this interface and register itself in the format detector.
 */
interface SpreadsheetReaderInterface
{
    /**
     * @return array{
     *     headers: array<int, string>,
     *     rows: array<int, array<int|string, mixed>>
     * }
     */
    public function read(string $path): array;
}
