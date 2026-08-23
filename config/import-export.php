<?php

use App\ImportExport\Definitions\CounterInvoiceDefinition;
use App\ImportExport\Definitions\CustomerDefinition;
use App\ImportExport\Definitions\EmployeeDefinition;
use App\ImportExport\Definitions\JobCardDefinition;
use App\ImportExport\Definitions\LabourMasterDefinition;
use App\ImportExport\Definitions\PaymentDefinition;
use App\ImportExport\Definitions\SparePartDefinition;
use App\ImportExport\Definitions\VehicleBrandDefinition;
use App\ImportExport\Definitions\VehicleModelDefinition;

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
        'spare_parts' => SparePartDefinition::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Export-only entities
    |--------------------------------------------------------------------------
    |
    | Entities that are exported through the same shared ExportService (and
    | thus get the data-table "Export selected" action) but are never offered
    | for import in the hub. Their definitions describe columns/types/query for
    | CSV output only; the import hooks throw "not implemented".
    |
    */

    'export_entities' => [
        'counter_invoices' => CounterInvoiceDefinition::class,
        'payments' => PaymentDefinition::class,
        'labour_masters' => LabourMasterDefinition::class,
        'employees' => EmployeeDefinition::class,
        'vehicle_models' => VehicleModelDefinition::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Module home routes
    |--------------------------------------------------------------------------
    |
    | Each importable entity's import/export lives on its own module index page
    | (there is no central Import/Export hub). This maps an entity key to the
    | route the import workflow returns to (e.g. the "Back" buttons on the
    | preview/result pages and the error redirects in the controller).
    |
    */

    'module_routes' => [
        'customers' => 'customers.index',
        'job_cards' => 'job-cards.index',
        'spare_parts' => 'inventory.index',
        'vehicle_brands' => 'vehicle-catalog.index',
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
    | CSV is the only supported format for imports and exports.
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
