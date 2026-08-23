<?php

namespace App\Http\Controllers\Concerns;

use App\ImportExport\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait BulkExportsRecords
{
    /**
     * Registry key of the entity this controller bulk-exports. The consuming
     * controller must implement it, e.g.:
     *
     *     protected function bulkExportEntity(): string
     *     {
     *         return 'customers';
     *     }
     *
     * The key must resolve to an import/export definition (registered under
     * config "entities" or "export_entities").
     */
    abstract protected function bulkExportEntity(): string;

    /**
     * Export the selected records as CSV in one request.
     *
     * Route pattern (registered inside the auth middleware group, alongside
     * the module's other routes):
     *
     *     Route::post('customers/bulk-export', [CustomerController::class, 'bulkExport'])
     *         ->name('customers.bulk-export');
     *
     * The shared ExportService reuses the entity's export columns/types/query
     * and narrows it to the given primary keys, so the file is a slice of the
     * full CSV export. Validation failures return the standard JSON shape (the
     * endpoint is only ever called from the frontend's fetch, not a form).
     */
    public function bulkExport(Request $request): StreamedResponse|JsonResponse
    {
        try {
            $validated = $request->validate([
                'ids' => ['required', 'array', 'min:1'],
                'ids.*' => ['integer'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return app(ExportService::class)->exportCsvForIds($this->bulkExportEntity(), $validated['ids']);
    }
}
