<?php

use App\ImportExport\Definitions\CustomerDefinition;
use App\ImportExport\Definitions\JobCardDefinition;
use App\ImportExport\Definitions\VehicleBrandDefinition;

return [

    /*
    |--------------------------------------------------------------------------
    | Import/Export entities
    |--------------------------------------------------------------------------
    |
    | Registry of importable/exportable entities. Each entry maps a stable
    | entity key (used in URLs and the UI) to a definition class that
    | implements App\ImportExport\Contracts\ImportDefinition.
    |
    | Vehicles and Employees are intentionally NOT registered here: they are
    | managed through the Customers page and the Vehicle Catalog, and are only
    | needed as private reference targets for Job Card imports (see the
    | definitions under App\ImportExport\Definitions).
    |
    */

    'entities' => [
        'customers' => CustomerDefinition::class,
        'vehicle_brands' => VehicleBrandDefinition::class,
        'job_cards' => JobCardDefinition::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload limits
    |--------------------------------------------------------------------------
    */

    'max_file_size_bytes' => 2 * 1024 * 1024, // 2 MB

    /*
    |--------------------------------------------------------------------------
    | Allowed file formats
    |--------------------------------------------------------------------------
    |
    | CSV is the only format currently enabled. Excel (.xlsx) support is
    | designed into the reader architecture (FileFormatDetector +
    | SpreadsheetReaderInterface) but requires PHP's ext-zip extension and a
    | spreadsheet library (e.g. phpoffice/phpspreadsheet), both of which are
    | not currently available in this environment. Once available, add the
    | format here and register an Xlsx reader.
    |
    */

    'allowed_extensions' => ['csv'],
    'allowed_mime_types' => [
        'text/csv',
        'text/x-csv',
        'text/plain',
        'application/csv',
        'application/x-csv',
        'application/vnd.ms-excel', // sometimes sent for .csv files
    ],
    'max_rows' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Uploaded files and generated error reports are kept on the private
    | "local" disk (storage/app/private). Uploaded originals are stored
    | byte-for-byte and never modified.
    |
    */

    'storage' => [
        'disk' => 'local',
        'uploads' => 'import-export/uploads',
        'sessions' => 'import-export/sessions',
        'error_reports' => 'import-export/error-reports',
    ],

];