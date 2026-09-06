<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Only add indexes if they don't already exist
            if (! Schema::hasIndex('payments', 'payments_payment_date_index')) {
                $table->index('payment_date');
            }
            if (! Schema::hasIndex('payments', 'payments_payment_mode_index')) {
                $table->index('payment_mode');
            }
            if (! Schema::hasIndex('payments', 'payments_payable_type_payable_id_index')) {
                $table->index(['payable_type', 'payable_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['payment_date']);
            $table->dropIndex(['payment_mode']);
            $table->dropIndex(['payable_type', 'payable_id']);
        });
    }
};
