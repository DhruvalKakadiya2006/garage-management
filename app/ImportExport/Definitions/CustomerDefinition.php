<?php

namespace App\ImportExport\Definitions;

use App\ImportExport\AbstractImportDefinition;
use App\Models\Customer;
use Illuminate\Support\Collection;

/**
 * Customer import/export.
 *
 * Columns are generated directly from the current customers schema (see
 * 2026_08_02_125700_create_customers_table.php and the refactor migration
 * 2026_08_06_090001). Machine-readable, stable headers are used — they double
 * as the import headers, so an exported file is re-importable without manual
 * column editing.
 *
 * Matching strategy:
 *  - Primary key: external_ref (the stable import/export identifier).
 *  - Safe fallback: mobile (unique business key).
 *  - Name-based matching is intentionally NOT used; ambiguous matches are
 *    reported as errors instead of silently merging customers.
 *
 * Imports create/update Customer rows only. Vehicles, job cards and invoices
 * are untouched — existing relationships are never modified by a Customer
 * import.
 */
class CustomerDefinition extends AbstractImportDefinition
{
    public function key(): string
    {
        return 'customers';
    }

    public function label(): string
    {
        return 'Customers';
    }

    public function description(): ?string
    {
        return 'Create or update customer records from a CSV file. Existing vehicles and job cards are never touched.';
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
                'header' => 'mobile',
                'field' => 'mobile',
                'label' => 'Mobile',
                'required' => true,
                'type' => 'string',
                'unique_in_file' => true,
                'rules' => ['regex:/^[6-9]\d{9}$/'],
            ],
            [
                'header' => 'alt_mobile',
                'field' => 'alt_mobile',
                'label' => 'Alt Mobile',
                'required' => false,
                'type' => 'string',
                'rules' => ['regex:/^[6-9]\d{9}$/'],
            ],
            [
                'header' => 'email',
                'field' => 'email',
                'label' => 'Email',
                'required' => false,
                'type' => 'string',
                'max_length' => 255,
                'rules' => ['email'],
            ],
            [
                'header' => 'first_name',
                'field' => 'first_name',
                'label' => 'First Name',
                'required' => true,
                'type' => 'string',
                'max_length' => 255,
            ],
            [
                'header' => 'last_name',
                'field' => 'last_name',
                'label' => 'Last Name',
                'required' => false,
                'type' => 'string',
                'max_length' => 255,
            ],
            [
                'header' => 'surname',
                'field' => 'surname',
                'label' => 'Surname',
                'required' => false,
                'type' => 'string',
                'max_length' => 255,
            ],
            [
                'header' => 'company_name',
                'field' => 'company_name',
                'label' => 'Company Name',
                'required' => false,
                'type' => 'string',
                'max_length' => 255,
            ],
            [
                'header' => 'display_name',
                'field' => 'display_name',
                'label' => 'Display Name',
                'required' => false,
                'type' => 'string',
                'max_length' => 255,
            ],
            [
                'header' => 'address',
                'field' => 'address',
                'label' => 'Address',
                'required' => false,
                'type' => 'string',
            ],
            [
                'header' => 'city',
                'field' => 'city',
                'label' => 'City',
                'required' => false,
                'type' => 'string',
                'max_length' => 100,
            ],
            [
                'header' => 'gstin',
                'field' => 'gstin',
                'label' => 'GSTIN',
                'required' => false,
                'type' => 'string',
                'rules' => ['regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
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

    public function importKeys(): array
    {
        return ['external_ref', 'mobile'];
    }

    public function resolveCandidates(array $data): Collection
    {
        return $this->resolveByKeys(Customer::class, $data, $this->importKeys());
    }

    public function applyCreate(array $data): object
    {
        [$fill, $timestamps] = $this->splitTimestamps($data);

        $customer = Customer::create($fill);

        $this->restoreTimestamps($customer, $timestamps);

        return $customer;
    }

    public function applyUpdate(object $record, array $data): object
    {
        [$fill, $timestamps] = $this->splitTimestamps($data);

        $record->update($fill);

        $this->restoreTimestamps($record, $timestamps);

        return $record;
    }

    public function exportColumns(): array
    {
        return [
            'external_ref' => 'external_ref',
            'mobile' => 'mobile',
            'alt_mobile' => 'alt_mobile',
            'email' => 'email',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'surname' => 'surname',
            'company_name' => 'company_name',
            'display_name' => 'display_name',
            'address' => 'address',
            'city' => 'city',
            'gstin' => 'gstin',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }

    public function exportQuery(array $filters = [])
    {
        return Customer::query()->orderBy('created_at')->orderBy('id');
    }

    public function exportFileName(): string
    {
        return 'customers';
    }

    /**
     * Realistic sample rows that pass every validation rule, so the template
     * is directly importable.
     *
     * @return array<int, array<string, mixed>>
     */
    public function templateRows(): array
    {
        return [
            [
                'external_ref' => 'CUS-0001',
                'mobile' => '9876543210',
                'alt_mobile' => '',
                'email' => 'arjun.patel@example.com',
                'first_name' => 'Arjun',
                'last_name' => 'Patel',
                'surname' => '',
                'company_name' => '',
                'display_name' => 'Arjun Patel',
                'address' => '12 Ring Road, Adajan',
                'city' => 'Surat',
                'gstin' => '24ABCDE1234F1Z5',
                'created_at' => '2026-01-05 10:30:00',
                'updated_at' => '2026-01-05 10:30:00',
            ],
            [
                'external_ref' => 'CUS-0002',
                'mobile' => '9876543211',
                'alt_mobile' => '9123456780',
                'email' => '',
                'first_name' => 'Meera',
                'last_name' => 'Shah',
                'surname' => '',
                'company_name' => 'Shah Electronics',
                'display_name' => 'Meera Shah',
                'address' => '45 Market Road',
                'city' => 'Surat',
                'gstin' => '',
                'created_at' => '2026-02-10 09:15:00',
                'updated_at' => '2026-02-10 09:15:00',
            ],
        ];
    }
}
