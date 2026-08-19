<?php

namespace App\ImportExport\Definitions;

use App\ImportExport\AbstractImportDefinition;
use App\ImportExport\ProcessedRow;
use App\ImportExport\ValidationIssue;
use App\Models\JobCard;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Job Card import/export.
 *
 * Columns mirror the current job_cards schema. References to other entities are
 * resolved through relations(): customers by external_ref or mobile, vehicles
 * by external_ref or registration_no, technicians/supervisors by external_ref.
 * A reference that matches nothing or several records is an error — never a
 * guess. The row-level validateRow hook additionally rejects a vehicle that
 * does not belong to the resolved customer (same rule as the web form).
 *
 * Matching for create-or-update uses external_ref then job_card_no. Imported
 * records keep their historical values: timestamps are preserved, grand_total
 * stays NULL when the cell is blank (an un-billed card), and derived money
 * fields are stored exactly as supplied rather than recalculated.
 *
 * Dependencies: Customers must be imported first, and referenced Vehicles and
 * Employees must already exist (create them from the Customers / Employees
 * pages) — the template rows reference CUS-0001 / VEH-0001 / TECH-0001.
 */
class JobCardDefinition extends AbstractImportDefinition
{
    public function key(): string
    {
        return 'job_cards';
    }

    public function label(): string
    {
        return 'Job Cards';
    }

    public function description(): ?string
    {
        return 'Create or update job cards from a CSV file. Import Customers first, then make sure every referenced vehicle and technician already exists.';
    }

    public function columns(): array
    {
        return [
            [
                'header' => 'external_ref',
                'field' => 'external_ref',
                'label' => 'External Ref',
                'required' => false,
                'type' => 'string',
                'max_length' => 255,
                'unique_in_file' => true,
            ],
            [
                'header' => 'job_card_no',
                'field' => 'job_card_no',
                'label' => 'Job Card No',
                'required' => false,
                'type' => 'string',
                'max_length' => 50,
                'unique_in_file' => true,
            ],
            [
                'header' => 'job_date',
                'field' => 'job_date',
                'label' => 'Job Date',
                'required' => true,
                'type' => 'date',
            ],
            [
                'header' => 'customer_external_id',
                'field' => 'customer_external_id',
                'label' => 'Customer External Ref',
                'required' => false,
                'type' => 'string',
                'max_length' => 255,
            ],
            [
                'header' => 'customer_mobile',
                'field' => 'customer_mobile',
                'label' => 'Customer Mobile',
                'required' => false,
                'type' => 'string',
                'rules' => ['regex:/^[6-9]\d{9}$/'],
            ],
            [
                'header' => 'vehicle_external_id',
                'field' => 'vehicle_external_id',
                'label' => 'Vehicle External Ref',
                'required' => false,
                'type' => 'string',
                'max_length' => 255,
            ],
            [
                'header' => 'vehicle_registration_no',
                'field' => 'vehicle_registration_no',
                'label' => 'Vehicle Registration No',
                'required' => false,
                'type' => 'string',
                'max_length' => 255,
            ],
            [
                'header' => 'technician_external_id',
                'field' => 'technician_external_id',
                'label' => 'Technician External Ref',
                'required' => false,
                'type' => 'string',
                'max_length' => 255,
            ],
            [
                'header' => 'supervisor_external_id',
                'field' => 'supervisor_external_id',
                'label' => 'Supervisor External Ref',
                'required' => false,
                'type' => 'string',
                'max_length' => 255,
            ],
            [
                'header' => 'odometer_reading_km',
                'field' => 'odometer_reading_km',
                'label' => 'Odometer (km)',
                'required' => false,
                'type' => 'integer',
            ],
            [
                'header' => 'job_type',
                'field' => 'job_type',
                'label' => 'Job Type',
                'required' => true,
                'type' => 'enum',
                'enum' => array_keys(config('garage.option_lists.job_type')),
            ],
            [
                'header' => 'promised_delivery_date',
                'field' => 'promised_delivery_date',
                'label' => 'Promised Delivery Date',
                'required' => false,
                'type' => 'date',
            ],
            [
                'header' => 'promised_delivery_time',
                'field' => 'promised_delivery_time',
                'label' => 'Promised Delivery Time',
                'required' => false,
                'type' => 'time',
            ],
            [
                'header' => 'status',
                'field' => 'status',
                'label' => 'Status',
                'required' => true,
                'type' => 'enum',
                'enum' => array_keys(config('garage.option_lists.job_card_status')),
            ],
            [
                'header' => 'discount_percent',
                'field' => 'discount_percent',
                'label' => 'Discount Percent',
                'required' => false,
                'type' => 'decimal',
            ],
            [
                'header' => 'discount_amount',
                'field' => 'discount_amount',
                'label' => 'Discount Amount',
                'required' => false,
                'type' => 'decimal',
            ],
            [
                'header' => 'gst_applicable',
                'field' => 'gst_applicable',
                'label' => 'GST Applicable',
                'required' => false,
                'type' => 'boolean',
            ],
            [
                'header' => 'cgst_amount',
                'field' => 'cgst_amount',
                'label' => 'CGST Amount',
                'required' => false,
                'type' => 'decimal',
            ],
            [
                'header' => 'sgst_amount',
                'field' => 'sgst_amount',
                'label' => 'SGST Amount',
                'required' => false,
                'type' => 'decimal',
            ],
            [
                'header' => 'round_off_amount',
                'field' => 'round_off_amount',
                'label' => 'Round Off Amount',
                'required' => false,
                'type' => 'decimal',
            ],
            [
                'header' => 'grand_total',
                'field' => 'grand_total',
                'label' => 'Grand Total',
                'required' => false,
                'type' => 'decimal',
            ],
            [
                'header' => 'remarks',
                'field' => 'remarks',
                'label' => 'Remarks',
                'required' => false,
                'type' => 'string',
            ],
            [
                'header' => 'invoice_generated_at',
                'field' => 'invoice_generated_at',
                'label' => 'Invoice Generated At',
                'required' => false,
                'type' => 'datetime',
            ],
            [
                'header' => 'estimate_generated_at',
                'field' => 'estimate_generated_at',
                'label' => 'Estimate Generated At',
                'required' => false,
                'type' => 'datetime',
            ],
            [
                'header' => 'created_at',
                'field' => 'created_at',
                'label' => 'Created At',
                'required' => false,
                'type' => 'datetime',
            ],
            [
                'header' => 'updated_at',
                'field' => 'updated_at',
                'label' => 'Updated At',
                'required' => false,
                'type' => 'datetime',
            ],
        ];
    }

    public function relations(): array
    {
        return [
            [
                'entity' => CustomerDefinition::class,
                'field' => 'customer_id',
                'keys' => ['customer_external_id' => 'external_ref', 'customer_mobile' => 'mobile'],
                'required' => true,
            ],
            [
                'entity' => VehicleDefinition::class,
                'field' => 'vehicle_id',
                'keys' => ['vehicle_external_id' => 'external_ref', 'vehicle_registration_no' => 'registration_no'],
                'required' => true,
            ],
            [
                'entity' => EmployeeDefinition::class,
                'field' => 'assigned_technician_id',
                'keys' => ['technician_external_id' => 'external_ref'],
                'required' => false,
            ],
            [
                'entity' => EmployeeDefinition::class,
                'field' => 'supervisor_id',
                'keys' => ['supervisor_external_id' => 'external_ref'],
                'required' => false,
            ],
        ];
    }

    public function validateRow(ProcessedRow $row): void
    {
        $customerId = $row->data['customer_id'] ?? null;
        $vehicleId = $row->data['vehicle_id'] ?? null;

        if ($customerId !== null && $vehicleId !== null) {
            $vehicle = Vehicle::find($vehicleId);

            if ($vehicle && (int) $vehicle->customer_id !== (int) $customerId) {
                $row->addIssue(ValidationIssue::error(
                    $row->rowNumber,
                    'vehicle_id',
                    $row->data['vehicle_external_id'] ?? $row->data['vehicle_registration_no'] ?? null,
                    'The vehicle does not belong to the referenced customer.'
                ));
            }
        }

        // min:0 via Laravel's validator compares absolute size, so a negative
        // odometer slips through — check it explicitly here.
        $odometer = $row->data['odometer_reading_km'] ?? null;
        if ($odometer !== null && (float) $odometer < 0) {
            $row->addIssue(ValidationIssue::error(
                $row->rowNumber,
                'odometer_reading_km',
                $row->data['odometer_reading_km'] ?? null,
                'Odometer (km) must be at least 0.'
            ));
        }
    }

    public function importKeys(): array
    {
        return ['external_ref', 'job_card_no'];
    }

    public function resolveCandidates(array $data): Collection
    {
        return $this->resolveByKeys(JobCard::class, $data, $this->importKeys());
    }

    public function applyCreate(array $data): object
    {
        $data = $this->normalizeMoneyFields($data);

        [$fill, $timestamps] = $this->splitTimestamps($data);

        $jobCard = JobCard::create($fill);

        $this->restoreTimestamps($jobCard, $timestamps);

        return $jobCard;
    }

    public function applyUpdate(object $record, array $data): object
    {
        $data = $this->normalizeMoneyFields($data);

        [$fill, $timestamps] = $this->splitTimestamps($data);

        $record->update($fill);

        $this->restoreTimestamps($record, $timestamps);

        return $record;
    }

    public function exportColumns(): array
    {
        return [
            'external_ref' => 'external_ref',
            'job_card_no' => 'job_card_no',
            'job_date' => 'job_date',
            'customer_external_id' => 'customer.external_ref',
            'customer_mobile' => 'customer.mobile',
            'vehicle_external_id' => 'vehicle.external_ref',
            'vehicle_registration_no' => 'vehicle.registration_no',
            'technician_external_id' => 'assignedTechnician.external_ref',
            'supervisor_external_id' => 'supervisor.external_ref',
            'odometer_reading_km' => 'odometer_reading_km',
            'job_type' => 'job_type',
            'promised_delivery_date' => 'promised_delivery_date',
            'promised_delivery_time' => 'promised_delivery_time',
            'status' => 'status',
            'discount_percent' => 'discount_percent',
            'discount_amount' => 'discount_amount',
            'gst_applicable' => 'gst_applicable',
            'cgst_amount' => 'cgst_amount',
            'sgst_amount' => 'sgst_amount',
            'round_off_amount' => 'round_off_amount',
            'grand_total' => 'grand_total',
            'remarks' => 'remarks',
            'invoice_generated_at' => 'invoice_generated_at',
            'estimate_generated_at' => 'estimate_generated_at',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }

    public function exportQuery(array $filters = [])
    {
        $query = JobCard::with(['customer', 'vehicle', 'assignedTechnician', 'supervisor'])
            ->orderBy('job_date')
            ->orderBy('id');

        if (! empty($filters['technician'])) {
            $query->where('assigned_technician_id', $filters['technician']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['brand'])) {
            $query->whereHas('vehicle.vehicleModel.vehicleBrand', fn ($q) => $q->where('name', $filters['brand']));
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('job_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('job_date', '<=', $filters['to_date']);
        }

        return $query;
    }

    public function exportFileName(): string
    {
        return 'job-cards';
    }

    /**
     * Realistic sample rows that pass validation, so the template is directly
     * importable — provided the referenced Customers, Vehicles and Employees
     * already exist (see the definition description and the migration guide).
     *
     * @return array<int, array<string, mixed>>
     */
    public function templateRows(): array
    {
        return [
            [
                'external_ref' => 'JC-EXP-0001',
                'job_card_no' => '',
                'job_date' => '2026-01-15',
                'customer_external_id' => 'CUS-0001',
                'customer_mobile' => '',
                'vehicle_external_id' => 'VEH-0001',
                'vehicle_registration_no' => '',
                'technician_external_id' => 'TECH-0001',
                'supervisor_external_id' => '',
                'odometer_reading_km' => '12500',
                'job_type' => 'paid_service',
                'promised_delivery_date' => '2026-01-16',
                'promised_delivery_time' => '18:00',
                'status' => 'received',
                'discount_percent' => '0',
                'discount_amount' => '0',
                'gst_applicable' => 'false',
                'cgst_amount' => '0',
                'sgst_amount' => '0',
                'round_off_amount' => '0',
                'grand_total' => '',
                'remarks' => 'Brake pad replacement and general service.',
                'invoice_generated_at' => '',
                'estimate_generated_at' => '',
                'created_at' => '2026-01-15 09:30:00',
                'updated_at' => '2026-01-15 09:30:00',
            ],
            [
                'external_ref' => 'JC-EXP-0002',
                'job_card_no' => '',
                'job_date' => '2026-02-20',
                'customer_external_id' => '',
                'customer_mobile' => '9876543210',
                'vehicle_external_id' => '',
                'vehicle_registration_no' => 'GJ-05-AB-1234',
                'technician_external_id' => '',
                'supervisor_external_id' => 'TECH-0002',
                'odometer_reading_km' => '18300',
                'job_type' => 'insurance_claim',
                'promised_delivery_date' => '',
                'promised_delivery_time' => '',
                'status' => 'in_progress',
                'discount_percent' => '5',
                'discount_amount' => '0',
                'gst_applicable' => 'true',
                'cgst_amount' => '0',
                'sgst_amount' => '0',
                'round_off_amount' => '0',
                'grand_total' => '4500',
                'remarks' => 'Bumper replacement under insurance claim.',
                'invoice_generated_at' => '',
                'estimate_generated_at' => '',
                'created_at' => '2026-02-20 11:45:00',
                'updated_at' => '2026-02-20 11:45:00',
            ],
        ];
    }

    /**
     * Job card money fields are NOT NULL at the schema level. Keep them at
     * their default (0 / false) when a row leaves the cell blank, on both
     * create and update. grand_total is deliberately excluded — a blank stays
     * NULL, which is what makes an un-billed card read as "unbilled".
     */
    private function normalizeMoneyFields(array $data): array
    {
        foreach (['discount_percent', 'discount_amount', 'cgst_amount', 'sgst_amount', 'round_off_amount'] as $field) {
            if (($data[$field] ?? null) === null) {
                $data[$field] = 0;
            }
        }

        if (($data['gst_applicable'] ?? null) === null) {
            $data['gst_applicable'] = false;
        }

        return $data;
    }
}