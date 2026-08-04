<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prevent DB-level cascade from deleting counter_invoice_items without
     * firing the CounterInvoiceItem::deleting stock-return event. Children must
     * be deleted via Eloquent by the controller so stock is returned.
     */
    public function up(): void
    {
        Schema::table('counter_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['counter_invoice_id']);
            $table->dropForeign(['spare_part_id']);

            $table->foreign('counter_invoice_id')->references('id')->on('counter_invoices')->onDelete('restrict');
            $table->foreign('spare_part_id')->references('id')->on('spare_parts')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('counter_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['counter_invoice_id']);
            $table->dropForeign(['spare_part_id']);

            $table->foreign('counter_invoice_id')->references('id')->on('counter_invoices')->onDelete('cascade');
            $table->foreign('spare_part_id')->references('id')->on('spare_parts')->onDelete('cascade');
        });
    }
};
