<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the secondary indexes every dashboard / statement / analytics
     * query relies on. SQLite does not index FK child columns automatically,
     * and none of these tables declared any beyond their unique document
     * numbers, so list filters and payment sums were full table scans.
     */
    public function up(): void
    {
        Schema::table('job_cards', function (Blueprint $table) {
            $table->index('customer_id');
            $table->index('vehicle_id');
            $table->index('assigned_technician_id');
            $table->index('supervisor_id');
            $table->index('status');
            $table->index('job_date');
        });

        Schema::table('counter_invoices', function (Blueprint $table) {
            $table->index('customer_id');
            $table->index('invoice_date');
        });

        // (payable_type, payable_id) is already indexed by morphs() on the
        // payments table, so only payment_date needs adding here.
        Schema::table('payments', function (Blueprint $table) {
            $table->index('payment_date');
        });

        Schema::table('job_card_parts', function (Blueprint $table) {
            $table->index('job_card_id');
            $table->index('spare_part_id');
        });

        Schema::table('job_card_labours', function (Blueprint $table) {
            $table->index('job_card_id');
        });

        Schema::table('counter_invoice_items', function (Blueprint $table) {
            $table->index('counter_invoice_id');
            $table->index('spare_part_id');
        });
    }

    public function down(): void
    {
        Schema::table('job_cards', function (Blueprint $table) {
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['vehicle_id']);
            $table->dropIndex(['assigned_technician_id']);
            $table->dropIndex(['supervisor_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['job_date']);
        });

        Schema::table('counter_invoices', function (Blueprint $table) {
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['invoice_date']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['payment_date']);
        });

        Schema::table('job_card_parts', function (Blueprint $table) {
            $table->dropIndex(['job_card_id']);
            $table->dropIndex(['spare_part_id']);
        });

        Schema::table('job_card_labours', function (Blueprint $table) {
            $table->dropIndex(['job_card_id']);
        });

        Schema::table('counter_invoice_items', function (Blueprint $table) {
            $table->dropIndex(['counter_invoice_id']);
            $table->dropIndex(['spare_part_id']);
        });
    }
};
