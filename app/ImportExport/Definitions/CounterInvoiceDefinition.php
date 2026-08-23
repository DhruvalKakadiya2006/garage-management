<?php

namespace App\ImportExport\Definitions;

use App\ImportExport\AbstractImportDefinition;
use App\Models\CounterInvoice;

/**
 * Counter Invoice — export-only definition.
 *
 * Registered under config "export_entities", so the shared ExportService drives
 * the data-table "Export selected" action but Counter Invoices are never
 * offered for import in the hub. Columns/types mirror the counter_invoices
 * schema so the CSV formats dates/decimals/booleans the same way as the fully
 * importable entities.
 */
class CounterInvoiceDefinition extends AbstractImportDefinition
{
    public function key(): string
    {
        return 'counter_invoices';
    }

    public function label(): string
    {
        return 'Counter Invoices';
    }

    public function description(): ?string
    {
        return 'Export-only entity for the Counter Invoices data table.';
    }

    public function columns(): array
    {
        return [
            ['header' => 'invoice_no', 'field' => 'invoice_no', 'label' => 'Invoice No', 'required' => false, 'type' => 'string', 'max_length' => 50],
            ['header' => 'invoice_date', 'field' => 'invoice_date', 'label' => 'Invoice Date', 'required' => false, 'type' => 'date'],
            ['header' => 'customer_external_id', 'field' => 'customer.external_ref', 'label' => 'Customer External Ref', 'required' => false, 'type' => 'string', 'max_length' => 255],
            ['header' => 'customer_mobile', 'field' => 'customer.mobile', 'label' => 'Customer Mobile', 'required' => false, 'type' => 'string', 'max_length' => 20],
            ['header' => 'walkin_name', 'field' => 'walkin_name', 'label' => 'Walk-in Name', 'required' => false, 'type' => 'string', 'max_length' => 255],
            ['header' => 'walkin_mobile', 'field' => 'walkin_mobile', 'label' => 'Walk-in Mobile', 'required' => false, 'type' => 'string', 'max_length' => 20],
            ['header' => 'gst_applicable', 'field' => 'gst_applicable', 'label' => 'GST Applicable', 'required' => false, 'type' => 'boolean'],
            ['header' => 'cgst_amount', 'field' => 'cgst_amount', 'label' => 'CGST Amount', 'required' => false, 'type' => 'decimal'],
            ['header' => 'sgst_amount', 'field' => 'sgst_amount', 'label' => 'SGST Amount', 'required' => false, 'type' => 'decimal'],
            ['header' => 'round_off_amount', 'field' => 'round_off_amount', 'label' => 'Round Off Amount', 'required' => false, 'type' => 'decimal'],
            ['header' => 'discount_percent', 'field' => 'discount_percent', 'label' => 'Discount Percent', 'required' => false, 'type' => 'decimal'],
            ['header' => 'discount_amount', 'field' => 'discount_amount', 'label' => 'Discount Amount', 'required' => false, 'type' => 'decimal'],
            ['header' => 'grand_total', 'field' => 'grand_total', 'label' => 'Grand Total', 'required' => false, 'type' => 'decimal'],
            ['header' => 'payment_mode', 'field' => 'payment_mode', 'label' => 'Payment Mode', 'required' => false, 'type' => 'string', 'max_length' => 50],
            ['header' => 'created_at', 'field' => 'created_at', 'label' => 'Created At', 'required' => false, 'type' => 'datetime'],
            ['header' => 'updated_at', 'field' => 'updated_at', 'label' => 'Updated At', 'required' => false, 'type' => 'datetime'],
        ];
    }

    public function exportColumns(): array
    {
        return [
            'invoice_no' => 'invoice_no',
            'invoice_date' => 'invoice_date',
            'customer_external_id' => 'customer.external_ref',
            'customer_mobile' => 'customer.mobile',
            'walkin_name' => 'walkin_name',
            'walkin_mobile' => 'walkin_mobile',
            'gst_applicable' => 'gst_applicable',
            'cgst_amount' => 'cgst_amount',
            'sgst_amount' => 'sgst_amount',
            'round_off_amount' => 'round_off_amount',
            'discount_percent' => 'discount_percent',
            'discount_amount' => 'discount_amount',
            'grand_total' => 'grand_total',
            'payment_mode' => 'payment_mode',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }

    public function exportQuery(array $filters = [])
    {
        return CounterInvoice::with('customer')
            ->orderBy('invoice_date')
            ->orderBy('id');
    }

    public function exportFileName(): string
    {
        return 'counter-invoices';
    }
}
