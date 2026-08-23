<?php

namespace App\ImportExport\Definitions;

use App\ImportExport\AbstractImportDefinition;
use App\Models\Payment;

/**
 * Payment — export-only definition.
 *
 * Registered under config "export_entities", so the shared ExportService drives
 * the data-table "Export selected" action but Payments are never offered for
 * import in the hub. The document/customer columns are read from the model's
 * accessors so an exported file shows the human-facing document reference
 * instead of a raw morph class name.
 */
class PaymentDefinition extends AbstractImportDefinition
{
    public function key(): string
    {
        return 'payments';
    }

    public function label(): string
    {
        return 'Payments';
    }

    public function description(): ?string
    {
        return 'Export-only entity for the Payments data table.';
    }

    public function columns(): array
    {
        return [
            ['header' => 'payment_date', 'field' => 'payment_date', 'label' => 'Payment Date', 'required' => false, 'type' => 'date'],
            ['header' => 'payment_mode', 'field' => 'payment_mode', 'label' => 'Payment Mode', 'required' => false, 'type' => 'string', 'max_length' => 50],
            ['header' => 'document_type', 'field' => 'document_type', 'label' => 'Document Type', 'required' => false, 'type' => 'string', 'max_length' => 50],
            ['header' => 'document_no', 'field' => 'document_no', 'label' => 'Document No', 'required' => false, 'type' => 'string', 'max_length' => 50],
            ['header' => 'customer', 'field' => 'customer_label', 'label' => 'Customer', 'required' => false, 'type' => 'string', 'max_length' => 255],
            ['header' => 'amount', 'field' => 'amount', 'label' => 'Amount', 'required' => false, 'type' => 'decimal'],
            ['header' => 'reference_no', 'field' => 'reference_no', 'label' => 'Reference', 'required' => false, 'type' => 'string', 'max_length' => 100],
            ['header' => 'notes', 'field' => 'notes', 'label' => 'Notes', 'required' => false, 'type' => 'string'],
            ['header' => 'created_at', 'field' => 'created_at', 'label' => 'Created At', 'required' => false, 'type' => 'datetime'],
            ['header' => 'updated_at', 'field' => 'updated_at', 'label' => 'Updated At', 'required' => false, 'type' => 'datetime'],
        ];
    }

    public function exportColumns(): array
    {
        return [
            'payment_date' => 'payment_date',
            'payment_mode' => 'payment_mode',
            'document_type' => 'document_type',
            'document_no' => 'document_no',
            'customer' => 'customer_label',
            'amount' => 'amount',
            'reference_no' => 'reference_no',
            'notes' => 'notes',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }

    public function exportQuery(array $filters = [])
    {
        return Payment::with('payable.customer')
            ->orderBy('payment_date')
            ->orderBy('id');
    }

    public function exportFileName(): string
    {
        return 'payments';
    }
}
