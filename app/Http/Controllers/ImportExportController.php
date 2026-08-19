<?php

namespace App\Http\Controllers;

use App\ImportExport\Enums\ImportMode;
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
 * Routes:
 *  - index          import/export hub (entity cards + upload form)
 *  - preview        POST upload → stores file + session, redirects to preview
 *  - preview/{token} dry-run report (safe to refresh; writes nothing)
 *  - confirm/{token} POST confirm → applies safe rows in a transaction
 *  - export         CSV download (Excel is a documented stub for now)
 *  - template       CSV import template (Excel is a documented stub for now)
 *  - error-report/{token} downloadable row-level issue list
 */
class ImportExportController extends Controller
{
    public function __construct(
        private readonly ImportRegistry $registry,
        private readonly ImportService $importService,
        private readonly ExportService $exportService,
    ) {
    }

    public function index(Request $request)
    {
        $entities = array_map(
            fn (array $entity) => $this->registry->details($entity['key']),
            $this->registry->available(),
        );

        $selected = (string) $request->query('entity', '');
        if ($selected !== '' && ! $this->registry->has($selected)) {
            $selected = '';
        }

        $limits = [
            'max_file_size' => config('import-export.max_file_size_bytes'),
            'allowed_extensions' => config('import-export.allowed_extensions', ['csv']),
            'max_rows' => config('import-export.max_rows'),
        ];

        return view('import-export.index', compact('entities', 'selected', 'limits'));
    }

    public function guide()
    {
        return view('import-export.guide');
    }

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
            $this->cleanup($token);

            return redirect()->route('import-export.index')->with('error', $e->getMessage());
        }

        return view('import-export.preview', compact('report', 'token'));
    }

    public function confirm(string $token)
    {
        try {
            $report = $this->importService->confirm($token);
        } catch (ImportException $e) {
            $this->cleanup($token);

            return redirect()->route('import-export.index')->with('error', $e->getMessage());
        }

        return view('import-export.result', compact('report'));
    }

    public function export(Request $request)
    {
        $entity = (string) $request->query('entity', '');
        $format = strtolower((string) $request->query('format', 'csv'));

        if (! $this->registry->has($entity)) {
            abort(404);
        }

        if ($format === 'xlsx') {
            return back()->with('error', 'Excel export requires enabling the PHP zip extension and adding a spreadsheet reader. CSV is fully supported.');
        }

        if ($format !== 'csv') {
            abort(422, 'Unsupported export format.');
        }

        return $this->exportService->exportCsv($entity, $request->query());
    }

    public function template(Request $request)
    {
        $entity = (string) $request->query('entity', '');
        $format = strtolower((string) $request->query('format', 'csv'));

        if (! $this->registry->has($entity)) {
            abort(404);
        }

        if ($format === 'xlsx') {
            return back()->with('error', 'Excel template requires enabling the PHP zip extension and adding a spreadsheet reader. CSV is fully supported.');
        }

        if ($format !== 'csv') {
            abort(422, 'Unsupported template format.');
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
}