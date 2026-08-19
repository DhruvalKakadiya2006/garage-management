<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class BulkDeleteService
{
    /**
     * Delete each record by id in a single request, catching failures per
     * record so one protected record never aborts the batch. Ids that no
     * longer exist are counted as skipped (this call did not delete them).
     *
     * The optional `$deleter` callable lets a module provide its own delete
     * routine when a plain `$record->delete()` is insufficient — e.g. Job
     * Cards and Counter Invoices must delete their item rows first (via
     * Eloquent, so stock is returned) before the parent, because the item
     * FKs are `restrict`. The deleter receives the loaded model and should
     * throw on failure; any Throwable is caught here and the record is
     * counted as skipped (never aborting the batch).
     *
     * Shared response shape — every module's bulk-delete endpoint returns
     * this exact structure so the shared frontend can report the result:
     *
     * {
     *     deleted_count: int,
     *     skipped_count: int,
     *     deleted_ids: string[],
     *     skipped_ids: string[],
     *     message: string,
     * }
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<int, int|string>  $ids
     * @param  callable(Model): void|null  $deleter
     * @return array{
     *     deleted_count: int,
     *     skipped_count: int,
     *     deleted_ids: array<int, string>,
     *     skipped_ids: array<int, string>,
     *     message: string,
     * }
     */
    public function delete(string $modelClass, array $ids, ?callable $deleter = null): array
    {
        $deleter ??= fn (Model $record) => $record->delete();

        $deletedIds = [];
        $skippedIds = [];

        foreach ($ids as $id) {
            $record = $modelClass::find($id);

            if (! $record) {
                $skippedIds[] = (string) $id;
                continue;
            }

            try {
                $deleter($record);
                $deletedIds[] = (string) $id;
            } catch (\Throwable $e) {
                $skippedIds[] = (string) $id;
            }
        }

        return $this->buildResponse($deletedIds, $skippedIds);
    }

    /**
     * @param  array<int, string>  $deletedIds
     * @param  array<int, string>  $skippedIds
     * @return array{
     *     deleted_count: int,
     *     skipped_count: int,
     *     deleted_ids: array<int, string>,
     *     skipped_ids: array<int, string>,
     *     message: string,
     * }
     */
    protected function buildResponse(array $deletedIds, array $skippedIds): array
    {
        $deletedCount = count($deletedIds);
        $skippedCount = count($skippedIds);

        $message = $deletedCount > 0
            ? "{$deletedCount} deleted"
            : 'No records deleted';

        if ($skippedCount > 0) {
            $message .= ", {$skippedCount} skipped (linked to existing records)";
        }

        return [
            'deleted_count' => $deletedCount,
            'skipped_count' => $skippedCount,
            'deleted_ids' => $deletedIds,
            'skipped_ids' => $skippedIds,
            'message' => $message,
        ];
    }
}