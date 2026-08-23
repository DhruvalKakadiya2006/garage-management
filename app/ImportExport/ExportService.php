<?php

namespace App\ImportExport;

use App\ImportExport\Contracts\ImportDefinition;
use App\ImportExport\Support\FormulaSanitizer;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export and import-template generation.
 *
 * Both use the exact same column list the importer understands
 * (definition.exportColumns() must equal the import columns' headers) so a
 * file the app exports is always directly re-importable without manual
 * column editing. CSV is the only supported format.
 */
class ExportService
{
    public function __construct(
        private readonly ImportRegistry $registry,
    ) {}

    /**
     * Stream a CSV export of the entity's exportQuery() rows.
     *
     * Rows are pulled lazily (chunked by the database) so large datasets never
     * all live in memory at once; only one chunk is materialized at a time.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportCsv(string $entityKey, array $filters = []): StreamedResponse
    {
        $definition = $this->registry->exportDefinitionFor($entityKey);

        $headers = array_keys($definition->exportColumns());

        $types = array_column($definition->columns(), 'type', 'header');

        return $this->stream($headers, function ($write) use ($definition, $headers, $types, $filters) {
            $this->writeRows($write, $definition, $headers, $types, $definition->exportQuery($filters)->lazy(500));
        }, $definition->exportFileName().'.csv');
    }

    /**
     * Stream a CSV of only the records with the given primary keys. Reuses the
     * same columns/types/formatting as the full export so a selection of rows
     * looks exactly like a slice of the full file.
     *
     * @param  array<int, int|string>  $ids
     */
    public function exportCsvForIds(string $entityKey, array $ids): StreamedResponse
    {
        $definition = $this->registry->exportDefinitionFor($entityKey);

        $headers = array_keys($definition->exportColumns());

        $types = array_column($definition->columns(), 'type', 'header');

        return $this->stream($headers, function ($write) use ($definition, $headers, $types, $ids) {
            $query = $definition->exportQuery();
            $keyName = $query->getModel()->getKeyName();
            $query->whereIn($keyName, $ids);

            $this->writeRows($write, $definition, $headers, $types, $query->lazy(500));
        }, $definition->exportFileName().'-selected.csv');
    }

    /**
     * Stream a CSV import template with the production headers and realistic
     * sample rows that pass validation, so the template is directly importable.
     */
    public function templateCsv(string $entityKey): StreamedResponse
    {
        $definition = $this->registry->exportDefinitionFor($entityKey);

        $headers = array_keys($definition->exportColumns());

        return $this->stream($headers, function ($write) use ($definition, $headers) {
            foreach ($definition->templateRows() as $row) {
                $values = [];
                foreach ($headers as $header) {
                    $values[] = FormulaSanitizer::sanitize($row[$header] ?? '');
                }
                $write($values);
            }
        }, $definition->exportFileName().'-import-template.csv');
    }

    /**
     * Render one formatted row per model, reading each exported column value
     * through data_get so relation paths (e.g. customer.external_ref) work.
     *
     * @param  callable(array<int, string>): void  $write
     * @param  array<int, string>  $headers
     * @param  array<string, string>  $types
     * @param  iterable<object>  $rows
     */
    private function writeRows(callable $write, ImportDefinition $definition, array $headers, array $types, iterable $rows): void
    {
        foreach ($rows as $row) {
            $values = [];
            foreach ($headers as $header) {
                $values[] = $this->formatValue(
                    data_get($row, $definition->exportColumns()[$header]),
                    $types[$header] ?? 'string'
                );
            }
            $write($values);
        }
    }

    private function stream(array $headers, callable $body, string $fileName): StreamedResponse
    {
        return new StreamedResponse(function () use ($headers, $body) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens the CSV with correct encoding.
            echo "\xEF\xBB\xBF";

            fputcsv($handle, $headers);

            $body(function (array $values) use ($handle) {
                fputcsv($handle, $values);
            });

            fclose($handle);
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Render an exported value according to its column type so the file's cell
     * contents match what the importer expects (dates as Y-m-d, times as H:i:s,
     * booleans as true/false, decimals with two places). Raw strings pass
     * through untouched.
     */
    private function formatValue(mixed $value, string $type): string
    {
        if ($value instanceof DateTimeInterface) {
            $value = match ($type) {
                'date' => $value->format('Y-m-d'),
                'time' => $value->format('H:i:s'),
                'datetime' => $value->format('Y-m-d H:i:s'),
                default => (string) $value,
            };
        } elseif ($type === 'boolean' && is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif ($type === 'decimal' && is_numeric($value)) {
            $value = number_format((float) $value, 2, '.', '');
        }

        return FormulaSanitizer::sanitize($value);
    }
}
