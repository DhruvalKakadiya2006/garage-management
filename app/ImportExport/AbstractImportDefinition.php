<?php

namespace App\ImportExport;

use App\ImportExport\Contracts\ImportDefinition;
use App\ImportExport\Exceptions\ImportException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared, format-agnostic behaviour for every importable/exportable entity.
 *
 * Subclasses supply the column specs, import keys and apply hooks; everything
 * else (lookups, default no-ops) lives here so the ImportService stays generic.
 */
abstract class AbstractImportDefinition implements ImportDefinition
{
    public function description(): ?string
    {
        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public function columns(): array
    {
        return [];
    }

    /** @return array<int, array{entity: string, field: string, keys: array<string, string>, required?: bool}> */
    public function relations(): array
    {
        return [];
    }

    public function validateRow(ProcessedRow $row): void
    {
        // Cross-field checks are entity-specific; most entities have none.
    }

    public function importKeys(): array
    {
        return [];
    }

    /** @return Collection<int, object> */
    public function resolveCandidates(array $data): Collection
    {
        return new Collection;
    }

    public function applyCreate(array $data): object
    {
        throw new ImportException("Import for '{$this->key()}' is not implemented.");
    }

    public function applyUpdate(object $record, array $data): object
    {
        throw new ImportException("Import for '{$this->key()}' is not implemented.");
    }

    /**
     * Private reference entities (vehicles, employees) are never exported as a
     * top-level file, so the default column list is empty.
     *
     * @return array<string, string>
     */
    public function exportColumns(): array
    {
        return [];
    }

    public function exportFileName(): string
    {
        return $this->key();
    }

    /**
     * Find the column spec whose header matches the file's header.
     * Exact match wins, then a case-insensitive fallback.
     *
     * @return array<string, mixed>|null
     */
    public function columnForHeader(string $header): ?array
    {
        foreach ($this->columns() as $spec) {
            if (($spec['header'] ?? '') === $header) {
                return $spec;
            }
        }

        $needle = mb_strtolower(trim($header));

        foreach ($this->columns() as $spec) {
            if (mb_strtolower($spec['header'] ?? '') === $needle) {
                return $spec;
            }
        }

        return null;
    }

    /**
     * Find a column spec by its normalized field name.
     *
     * @return array<string, mixed>|null
     */
    public function columnByField(string $field): ?array
    {
        foreach ($this->columns() as $spec) {
            if (($spec['field'] ?? '') === $field) {
                return $spec;
            }
        }

        return null;
    }

    /** Expected header names, in file order. */
    public function expectedHeaders(): array
    {
        return array_map(fn (array $spec) => $spec['header'], $this->columns());
    }

    /** Whether a raw file row consists entirely of blank cells. */
    public function rowIsEmpty(array $rawRow): bool
    {
        foreach ($rawRow as $cell) {
            if (is_string($cell) ? trim($cell) !== '' : $cell !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Split timestamp fields out of a normalized row so they can be preserved
     * explicitly instead of being silently overwritten by Eloquent.
     *
     * @param  array<int, string>  $timestampKeys
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function splitTimestamps(array $data, array $timestampKeys = ['created_at', 'updated_at']): array
    {
        return [
            array_diff_key($data, array_flip($timestampKeys)),
            array_intersect_key($data, array_flip($timestampKeys)),
        ];
    }

    /**
     * Persist the explicitly preserved timestamps without Eloquent's own
     * updated_at handling kicking in.
     *
     * @param  array<string, mixed>  $timestamps
     */
    protected function restoreTimestamps(object $record, array $timestamps): void
    {
        if ($timestamps === []) {
            return;
        }

        $record->timestamps = false;
        $record->forceFill($timestamps)->save();
    }

    /**
     * Generic candidate lookup across the given import keys.
     *
     * Every non-empty key contributes an OR condition, matched exactly or
     * case-insensitively. When no key has a value, no records are returned (a
     * blank match must never select every row).
     *
     * Definitions use this for entities whose keys are simple columns. Entities
     * with compound/nested keys override resolveCandidates() themselves.
     *
     * @param  array<int, string>  $keys
     * @return Collection<int, object>
     */
    protected function resolveByKeys(string $modelClass, array $data, array $keys): Collection
    {
        $hasCondition = false;

        $query = $modelClass::query()->where(function ($q) use ($data, $keys, &$hasCondition) {
            foreach ($keys as $key) {
                $value = $data[$key] ?? null;

                if ($value === null || $value === '') {
                    continue;
                }

                $hasCondition = true;
                $q->orWhere($key, $value)
                    ->orWhere(DB::raw('LOWER('.$key.')'), mb_strtolower((string) $value));
            }
        });

        if (! $hasCondition) {
            return new Collection;
        }

        return $query->get();
    }
}
