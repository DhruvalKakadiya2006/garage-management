<?php

namespace App\Http\Controllers\Concerns;

use App\Services\BulkDeleteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait BulkDeletesRecords
{
    /**
     * Model class this controller bulk-deletes. The consuming controller must
     * implement it, e.g.:
     *
     *     protected function bulkDeleteModel(): string
     *     {
     *         return Customer::class;
     *     }
     */
    abstract protected function bulkDeleteModel(): string;

    /**
     * Optional per-record delete routine for models whose plain delete() is
     * not enough (e.g. Job Cards / Counter Invoices must delete their item
     * rows first so stock returns). Returns null to use `$record->delete()`.
     */
    protected function bulkDeleteDeleter(): ?callable
    {
        return null;
    }

    /**
     * Delete many records in one request instead of one request per row.
     *
     * Route pattern (registered inside the auth middleware group, alongside
     * the module's other routes):
     *
     *     Route::post('customers/bulk-delete', [CustomerController::class, 'bulkDestroy'])
     *         ->name('customers.bulk-delete');
     *
     * Each deletion is attempted individually and a failure (e.g. a
     * restrict-FK violation) is caught per record — the batch never aborts on
     * the first protected record. Shared JSON response shape — same for every
     * module:
     *
     * {
     *     deleted_count: int,
     *     skipped_count: int,
     *     deleted_ids: string[],
     *     skipped_ids: string[],
     *     message: string,
     * }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ids' => ['required', 'array', 'min:1'],
                'ids.*' => ['integer'],
            ]);
        } catch (ValidationException $e) {
            // The bulk-delete endpoint is JSON-only; the app's global exception
            // handler renders JSON solely for api/* routes, so a validation
            // failure here would otherwise come back as a redirect (302).
            // Return the standard JSON validation shape so the frontend's
            // bulkDelete() can surface the message.
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $result = app(BulkDeleteService::class)->delete(
            $this->bulkDeleteModel(),
            $validated['ids'],
            $this->bulkDeleteDeleter()
        );

        return response()->json($result);
    }
}