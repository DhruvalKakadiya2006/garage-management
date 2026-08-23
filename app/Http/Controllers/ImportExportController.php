<?php

namespace App\Http\Controllers;

use App\ImportExport\Exceptions\ImportException;
use App\ImportExport\ExportService;
use App\ImportExport\ImportRegistry;
use App\ImportExport\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Web UI for the reusable import/export foundation.
 *
 * There is no central Import/Export hub: each module's index page hosts its
 * own import upload (a shared modal that POSTs to preview) and export actions,
 * pointing at these entity-scoped routes with their own entity key.
 *
 * Routes:
 *  - preview        POST upload → stores file + session, redirects to preview
 *  - preview/{token} dry-run report (safe to refresh; writes nothing)
 *  - confirm/{token} POST confirm → applies safe rows in a transaction
 *  - export         CSV download (entity query param)
 *  - template       CSV import template (entity query param)
 *  - error-report/{token} downloadable row-level issue list
 */
class ImportExportController extends Controller
{
    public function __construct(
        private readonly ImportRegistry $registry,
        private readonly ImportService $importService,
        private readonly ExportService $exportService,
    ) {}

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'entity' => 'required|string',
            'mode' => 'required|in:create_only,create_or_update',
            'file' => ['required', 'file', 'max:'.(int) (config('import-export.max_file_size_bytes') / 1024)],
        ]);

        if (! $this->registry->has($validated['entity'])) {
            return back()->with('error', 'Unknown import/export entity.');
        }

        $file = $request->file('file');
        $token = (string) Str::uuid();
        $extension = strtolower((string) $file->getClientOriginalExtension());

        $disk = Storage::disk(config('import-export.storage.disk'));

        $uploadPath = $disk->putFileAs(
            config('import-export.storage.uploads'),
            $file,
            $token.'.'.$extension,
        );

        $this->importService->storeSession($token, [
            'entity_key' => $validated['entity'],
            'mode' => $validated['mode'],
            'original_filename' => $file->getClientOriginalName(),
            'upload_path' => $uploadPath,
        ]);

        return redirect()->route('import-export.preview.show', $token);
    }

    public function showPreview(string $token)
    {
        try {
            $report = $this->importService->previewForToken($token);
        } catch (ImportException $e) {
            $home = $this->moduleRouteFor($this->entityKeyForToken($token));
            $this->cleanup($token);

            return redirect()->route($home)->with('error', $e->getMessage());
        }

        return view('import-export.preview', compact('report', 'token'));
    }

    public function confirm(Request $request, string $token)
    {
        try {
            $report = $this->importService->confirm($token);
        } catch (ImportException $e) {
            // The session file may already be gone (e.g. a replayed/expired
            // token), so fall back to the entity the preview form posted.
            $home = $this->moduleRouteFor(
                $request->input('entity') ?: $this->entityKeyForToken($token)
            );
            $this->cleanup($token);

            return redirect()->route($home)->with('error', $e->getMessage());
        }

        return view('import-export.result', compact('report'));
    }

    public function export(Request $request)
    {
        $entity = (string) $request->query('entity', '');

        if (! $this->registry->hasExport($entity)) {
            abort(404);
        }

        return $this->exportService->exportCsv($entity, $request->query());
    }

    public function template(Request $request)
    {
        $entity = (string) $request->query('entity', '');

        if (! $this->registry->has($entity)) {
            abort(404);
        }

        return $this->exportService->templateCsv($entity);
    }

    public function errorReport(string $token)
    {
        $disk = Storage::disk(config('import-export.storage.disk'));
        $path = config('import-export.storage.error_reports').'/'.$token.'.csv';

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->download($path, 'import-issues-'.$token.'.csv');
    }

    private function cleanup(string $token): void
    {
        $disk = Storage::disk(config('import-export.storage.disk'));
        $sessionPath = config('import-export.storage.sessions').'/'.$token.'.json';

        if (! $disk->exists($sessionPath)) {
            return;
        }

        $session = json_decode($disk->get($sessionPath), true);

        if (is_array($session) && isset($session['upload_path'])) {
            $disk->delete($session['upload_path']);
        }

        $disk->delete($sessionPath);
    }

    /**
     * Entity key for a stored upload session, so a failed preview/confirm can
     * redirect back to the module the import was started from.
     */
    private function entityKeyForToken(string $token): ?string
    {
        $disk = Storage::disk(config('import-export.storage.disk'));
        $path = config('import-export.storage.sessions').'/'.$token.'.json';

        if (! $disk->exists($path)) {
            return null;
        }

        $session = json_decode($disk->get($path), true);

        return is_array($session) ? ($session['entity_key'] ?? null) : null;
    }

    /**
     * Module index route that hosts a given entity's import/export actions.
     */
    private function moduleRouteFor(?string $entityKey): string
    {
        return config("import-export.module_routes.{$entityKey}", 'dashboard');
    }
}
