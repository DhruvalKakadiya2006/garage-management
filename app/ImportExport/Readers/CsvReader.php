<?php

namespace App\ImportExport\Readers;

use App\ImportExport\Exceptions\ImportException;

/**
 * CSV reader using PHP's fgetcsv.
 *
 * Treats every cell as literal data — no formula evaluation ever happens on
 * import. Handles the UTF-8 BOM that Excel prepends to CSV exports, and
 * tolerates \r\n / \n line endings.
 */
class CsvReader implements SpreadsheetReaderInterface
{
    public function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw ImportException::staleSession();
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new ImportException('Unable to read the uploaded file.');
        }

        try {
            $contents = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }

        if ($contents === false || $contents === '') {
            return ['headers' => [], 'rows' => []];
        }

        // Strip UTF-8 BOM if present.
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        // Normalize exotic line endings so fgetcsv's row splitting is reliable.
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);

        $lines = explode("\n", $contents);

        $headers = [];
        $rows = [];

        $first = true;
        $headerCount = null;

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $fields = str_getcsv($line);

            if ($first) {
                $headers = array_map(fn ($h) => trim((string) $h), $fields);
                $headerCount = count($headers);
                $first = false;
                continue;
            }

            $rows[] = $fields;
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }
}