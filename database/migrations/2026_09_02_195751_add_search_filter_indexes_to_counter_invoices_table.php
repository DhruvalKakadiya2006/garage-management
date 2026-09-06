<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counter_invoices', function (Blueprint $table) {
            // Only add indexes if they don't already exist
            if (! Schema::hasIndex('counter_invoices', 'counter_invoices_invoice_no_index')) {
                $table->index('invoice_no');
            }
            if (! Schema::hasIndex('counter_invoices', 'counter_invoices_invoice_date_index')) {
                $table->index('invoice_date');
            }
            if (! Schema::hasIndex('counter_invoices', 'counter_invoices_customer_id_index')) {
                $table->index('customer_id');
            }
            $table->index(['customer_id', 'invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::table('counter_invoices', function (Blueprint $table) {
            $table->dropIndex(['invoice_no']);
            $table->dropIndex(['invoice_date']);
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['customer_id', 'invoice_date']);
        });
    }
};
