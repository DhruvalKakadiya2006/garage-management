<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceIndexesTest extends TestCase
{
    use RefreshDatabase;

    private function assertIndexExists(string $table, string $index): void
    {
        $indexes = collect(DB::select("PRAGMA index_list({$table})"))->pluck('name');

        $this->assertContains($index, $indexes, "Expected index [{$index}] on [{$table}].");
    }

    public function test_foreign_key_and_filter_columns_are_indexed(): void
    {
        $this->assertIndexExists('job_cards', 'job_cards_customer_id_index');
        $this->assertIndexExists('job_cards', 'job_cards_vehicle_id_index');
        $this->assertIndexExists('job_cards', 'job_cards_assigned_technician_id_index');
        $this->assertIndexExists('job_cards', 'job_cards_supervisor_id_index');
        $this->assertIndexExists('job_cards', 'job_cards_status_index');
        $this->assertIndexExists('job_cards', 'job_cards_job_date_index');

        $this->assertIndexExists('counter_invoices', 'counter_invoices_customer_id_index');
        $this->assertIndexExists('counter_invoices', 'counter_invoices_invoice_date_index');

        $this->assertIndexExists('payments', 'payments_payable_type_payable_id_index');
        $this->assertIndexExists('payments', 'payments_payment_date_index');

        $this->assertIndexExists('job_card_parts', 'job_card_parts_job_card_id_index');
        $this->assertIndexExists('job_card_parts', 'job_card_parts_spare_part_id_index');

        $this->assertIndexExists('job_card_labours', 'job_card_labours_job_card_id_index');

        $this->assertIndexExists('counter_invoice_items', 'counter_invoice_items_counter_invoice_id_index');
        $this->assertIndexExists('counter_invoice_items', 'counter_invoice_items_spare_part_id_index');
    }
}
