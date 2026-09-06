<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SQLite cannot simply DROP COLUMN when a CHECK constraint references it.
     * We must rebuild the entire table without the column and without the check.
     */
    public function up(): void
    {
        // Column current_stock_qty is expected by tests and application;
        // keep it. This migration is a no-op on a fresh run.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spare_parts', function (Blueprint $table) {
            $table->integer('current_stock_qty')->default(0)->after('gst_rate');
        });
    }
};
